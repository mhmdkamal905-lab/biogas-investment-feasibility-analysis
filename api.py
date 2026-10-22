from flask import Flask, request, jsonify
from flask_cors import CORS

from src.models import TechnicalInputs, FinancialInputs, run_model
from src.storage import save_run, load_runs, db_status

import pandas as pd
import math

app = Flask(__name__)
CORS(app, origins=["http://localhost", "http://127.0.0.1"])


def clean_json(value):
    """Ubah nilai model menjadi format JSON yang aman."""
    if isinstance(value, dict):
        return {str(k): clean_json(v) for k, v in value.items()}

    if isinstance(value, (list, tuple)):
        return [clean_json(v) for v in value]

    if isinstance(value, pd.DataFrame):
        return clean_json(value.to_dict(orient="records"))

    if hasattr(value, "item"):
        value = value.item()

    if isinstance(value, float) and not math.isfinite(value):
        return None

    return value


def calculate(data):
    tech = TechnicalInputs(
        waste_tpd=float(data["waste_tpd"]),
        organic_fraction=float(data["organic_pct"]) / 100,
        reject_fraction=float(data["reject_pct"]) / 100,
        ts_fraction=float(data["ts_pct"]) / 100,
        vs_fraction_of_ts=float(data["vs_ts_pct"]) / 100,
        methane_yield_nm3_per_kg_vs=float(data["methane_yield"]),
        process_realization=float(data["realization_pct"]) / 100,
        methane_fraction=float(data["methane_content_pct"]) / 100,
        operating_days=int(data["operating_days"]),
        methane_lhv_kwh_nm3=float(data["lhv"]),
        generator_efficiency=float(data["electric_eff_pct"]) / 100,
        parasitic_energy_fraction=float(data["parasitic_pct"]) / 100,
    )

    fin = FinancialInputs(
        capex_rp=float(data["capex_m"]) * 1_000_000,
        fixed_opex_year_rp=float(data["fixed_opex_m"]) * 1_000_000,
        variable_cost_rp_per_kg=float(data["variable_cost_kg"]),
        electricity_price_rp_per_kwh=float(data["electricity_price"]),
        waste_service_fee_rp_per_kg=float(data["waste_fee_kg"]),
        other_revenue_year_rp=float(data["other_revenue_m"]) * 1_000_000,
        discount_rate=float(data["discount_pct"]) / 100,
        project_years=int(data["project_years"]),
    )

    result = run_model(tech, fin)

    indicators = {
        "Sampah organik": f'{result["organic_tpd"]:.2f} ton/hari',
        "Bahan layak": f'{result["feedstock_tpd"]:.2f} ton/hari',
        "VS tersedia": f'{result["vs_kg_day"]:,.0f} kg VS/hari',
        "Metana": f'{result["methane_nm3_day"]:,.0f} Nm³/hari',
        "Biogas teoritis": f'{result["biogas_nm3_day"]:,.0f} Nm³/hari',
        "Energi termal metana": f'{result["thermal_kwh_day"]:,.0f} kWh/hari',
        "Listrik bruto": f'{result["gross_electricity_kwh_day"]:,.0f} kWh/hari',
        "Listrik bersih": f'{result["net_electricity_kwh_day"]:,.0f} kWh/hari',
        "Pendapatan energi": result["energy_revenue_year_rp"],
        "Pendapatan layanan": result["waste_fee_revenue_year_rp"],
        "Arus kas operasi": result["operating_profit_year_rp"],
    }

    return result, indicators


@app.get("/api/status")
def status():
    return jsonify({
        "ok": True,
        "service": "WasteIQ Python API",
        "database": str(db_status()),
    })


@app.post("/api/analyze")
def analyze():
    try:
        data = request.get_json(silent=True) or {}
        result, indicators = calculate(data)

        return jsonify({
            "ok": True,
            "result": clean_json(result),
            "indicators": clean_json(indicators),
        })

    except (KeyError, TypeError, ValueError) as exc:
        return jsonify({
            "ok": False,
            "error": f"Parameter input tidak valid: {exc}",
        }), 400

    except Exception:
        app.logger.exception("Analisis WasteIQ gagal")
        return jsonify({
            "ok": False,
            "error": "Perhitungan gagal. Periksa model dan parameter input.",
        }), 500


@app.post("/api/save")
def save():
    try:
        data = request.get_json(silent=True) or {}
        result, indicators = calculate(data)

        record = {
            "waste_tpd": float(data["waste_tpd"]),
            "feedstock_tpd": float(result["feedstock_tpd"]),
            "methane_nm3_day": float(result["methane_nm3_day"]),
            "net_electricity_kwh_day": float(
                result["net_electricity_kwh_day"]
            ),
            "revenue_year_rp": float(result["revenue_year_rp"]),
            "opex_year_rp": float(result["opex_year_rp"]),
            "operating_profit_year_rp": float(
                result["operating_profit_year_rp"]
            ),
            "npv_rp": float(result["npv_rp"]),
            "irr": (
                float(result["irr"])
                if result["irr"] is not None
                else None
            ),
            "indicators": indicators,
        }

        save_run(record)

        return jsonify({
            "ok": True,
            "message": "Simulasi berhasil disimpan.",
        })

    except Exception:
        app.logger.exception("Penyimpanan simulasi gagal")
        return jsonify({
            "ok": False,
            "error": "Gagal menyimpan simulasi. Periksa koneksi dan skema MySQL.",
        }), 500


@app.get("/api/history")
def history():
    try:
        records = load_runs()

        if records is None or records.empty:
            rows = []
        else:
            rows = records.astype(object).where(
                pd.notna(records), None
            ).to_dict(orient="records")

        return jsonify({
            "ok": True,
            "records": clean_json(rows),
        })

    except Exception:
        app.logger.exception("Riwayat gagal dimuat")
        return jsonify({
            "ok": False,
            "error": "Riwayat tidak dapat dimuat dari database.",
        }), 500


if __name__ == "__main__":
    app.run(
        host="127.0.0.1",
        port=5001,
        debug=False,
    )
