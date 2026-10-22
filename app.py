import streamlit as st
import pandas as pd
import plotly.express as px

from src.models import TechnicalInputs, FinancialInputs, run_model
from src.storage import save_run, load_runs, db_status

st.set_page_config(
    page_title="WasteIQ",
    page_icon="♻️",
    layout="wide",
)

st.title("♻️ WasteIQ Business Intelligence")
st.caption(
    "Analisis awal sampah organik, produksi biogas, energi, "
    "dan kelayakan finansial."
)

# =========================================================
# 1. INPUT PARAMETER
# =========================================================
with st.sidebar:
    st.header("Parameter teknis")

    waste_tpd = st.number_input(
        "Sampah masuk (ton/hari)",
        min_value=0.1, value=5.0, step=0.5,
    )
    organic_pct = st.slider("Fraksi organik (%)", 0, 100, 60)
    reject_pct = st.slider(
        "Kontaminasi / reject (%)", 0, 80, 15
    )
    ts_pct = st.slider("Total solids / TS (%)", 1, 60, 20)
    vs_ts_pct = st.slider("VS sebagai % TS", 1, 100, 80)

    methane_yield = st.number_input(
        "Potensi metana (Nm³/kg VS)",
        min_value=0.0, value=0.25, step=0.01,
        format="%.2f",
    )
    realization_pct = st.slider(
        "Faktor realisasi proses (%)", 1, 100, 75
    )
    methane_content_pct = st.slider(
        "Kadar metana dalam biogas (%)", 30, 80, 60
    )
    operating_days = st.number_input(
        "Hari operasi per tahun",
        min_value=1, max_value=365, value=330,
    )

    st.divider()
    st.header("Parameter energi")

    lhv = st.number_input(
        "Nilai kalor metana (kWh/Nm³)",
        min_value=1.0, value=9.94, step=0.1,
    )
    electric_eff_pct = st.slider(
        "Efisiensi generator (%)", 1, 60, 35
    )
    parasitic_pct = st.slider(
        "Energi internal fasilitas (%)", 0, 50, 12
    )

    st.divider()
    st.header("Parameter finansial")

    capex_m = st.number_input(
        "Investasi awal CAPEX (juta Rp)",
        min_value=0.0, value=2500.0, step=100.0,
    )
    fixed_opex_m = st.number_input(
        "OPEX tetap per tahun (juta Rp)",
        min_value=0.0, value=250.0, step=25.0,
    )
    variable_cost_kg = st.number_input(
        "Biaya variabel (Rp/kg bahan layak)",
        min_value=0.0, value=150.0, step=10.0,
    )
    electricity_price = st.number_input(
        "Nilai listrik (Rp/kWh)",
        min_value=0.0, value=1200.0, step=100.0,
    )
    waste_fee_kg = st.number_input(
        "Pendapatan layanan sampah (Rp/kg)",
        min_value=0.0, value=0.0, step=50.0,
    )
    other_revenue_m = st.number_input(
        "Pendapatan lain per tahun (juta Rp)",
        min_value=0.0, value=0.0, step=25.0,
    )
    discount_pct = st.slider(
        "Tingkat diskonto (%)", 1, 30, 12
    )
    project_years = st.slider(
        "Umur proyek (tahun)", 1, 30, 15
    )

# =========================================================
# 2. JALANKAN MODEL
# =========================================================
tech = TechnicalInputs(
    waste_tpd=float(waste_tpd),
    organic_fraction=organic_pct / 100,
    reject_fraction=reject_pct / 100,
    ts_fraction=ts_pct / 100,
    vs_fraction_of_ts=vs_ts_pct / 100,
    methane_yield_nm3_per_kg_vs=float(methane_yield),
    process_realization=realization_pct / 100,
    methane_fraction=methane_content_pct / 100,
    operating_days=int(operating_days),
    methane_lhv_kwh_nm3=float(lhv),
    generator_efficiency=electric_eff_pct / 100,
    parasitic_energy_fraction=parasitic_pct / 100,
)

fin = FinancialInputs(
    capex_rp=float(capex_m) * 1_000_000,
    fixed_opex_year_rp=float(fixed_opex_m) * 1_000_000,
    variable_cost_rp_per_kg=float(variable_cost_kg),
    electricity_price_rp_per_kwh=float(electricity_price),
    waste_service_fee_rp_per_kg=float(waste_fee_kg),
    other_revenue_year_rp=float(other_revenue_m) * 1_000_000,
    discount_rate=discount_pct / 100,
    project_years=int(project_years),
)

result = run_model(tech, fin)

st.info(
    "Model ini merupakan screening awal. Validasi data TS/VS, "
    "uji BMP, biaya aktual, dan kontrak energi sebelum keputusan investasi."
)

# =========================================================
# 3. KPI UTAMA
# =========================================================
def rupiah_juta(value):
    return f"Rp {value / 1_000_000:,.1f} juta"

k1, k2, k3, k4 = st.columns(4)

k1.metric(
    "Bahan layak",
    f"{result['feedstock_tpd']:,.2f} ton/hari",
)
k2.metric(
    "Produksi metana",
    f"{result['methane_nm3_day']:,.0f} Nm³/hari",
)
k3.metric(
    "Listrik bersih",
    f"{result['net_electricity_kwh_day']:,.0f} kWh/hari",
)
k4.metric(
    "Laba operasi tahunan",
    rupiah_juta(result["operating_profit_year_rp"]),
)

# =========================================================
# 4. GRAFIK TEKNIS
# =========================================================
st.subheader("Neraca bahan dan produksi energi")

c1, c2 = st.columns(2)

with c1:
    mass_df = pd.DataFrame({
        "Komponen": [
            "Sampah masuk",
            "Fraksi organik",
            "Bahan layak diproses",
        ],
        "Ton/hari": [
            waste_tpd,
            result["organic_tpd"],
            result["feedstock_tpd"],
        ],
    })
    st.plotly_chart(
        px.bar(
            mass_df,
            x="Komponen",
            y="Ton/hari",
            title="Tahap pemilahan sampah",
        ),
        use_container_width=True,
    )

with c2:
    energy_df = pd.DataFrame({
        "Jenis": ["Listrik bruto", "Listrik bersih"],
        "kWh/hari": [
            result["gross_electricity_kwh_day"],
            result["net_electricity_kwh_day"],
        ],
    })
    st.plotly_chart(
        px.bar(
            energy_df,
            x="Jenis",
            y="kWh/hari",
            title="Estimasi energi listrik",
        ),
        use_container_width=True,
    )

# =========================================================
# 5. RINGKASAN FINANSIAL
# =========================================================
st.subheader("Ringkasan finansial")

f1, f2, f3, f4 = st.columns(4)

f1.metric(
    "Pendapatan tahunan",
    rupiah_juta(result["revenue_year_rp"]),
)
f2.metric(
    "OPEX tahunan",
    rupiah_juta(result["opex_year_rp"]),
)
f3.metric(
    "NPV",
    rupiah_juta(result["npv_rp"]),
)
f4.metric(
    "IRR",
    "Tidak terdefinisi"
    if result["irr"] is None
    else f"{result['irr'] * 100:.1f}%",
)

payback = result["simple_payback_years"]
st.write(
    "**Payback sederhana:** "
    + (
        "Tidak tercapai pada arus kas sederhana"
        if payback is None
        else f"{payback:.1f} tahun"
    )
)
st.write(
    "**Ambang pendapatan untuk menutup OPEX:** "
    + rupiah_juta(result["opex_year_rp"])
    + "/tahun; belum termasuk pengembalian CAPEX."
)

cash_df = pd.DataFrame(result["cashflow"])

st.plotly_chart(
    px.line(
        cash_df,
        x="year",
        y="cumulative_cashflow_rp",
        markers=True,
        title="Arus kas kumulatif",
    ),
    use_container_width=True,
)

# =========================================================
# 6. TABEL INDIKATOR - KURUNG SUDAH DIPISAH AGAR JELAS
# =========================================================
indicators = {
    "Sampah organik": f"{result['organic_tpd']:.2f} ton/hari",
    "Bahan layak": f"{result['feedstock_tpd']:.2f} ton/hari",
    "VS tersedia": f"{result['vs_kg_day']:,.0f} kg VS/hari",
    "Metana": f"{result['methane_nm3_day']:,.0f} Nm³/hari",
    "Biogas teoritis": f"{result['biogas_nm3_day']:,.0f} Nm³/hari",
    "Energi termal metana": f"{result['thermal_kwh_day']:,.0f} kWh/hari",
    "Listrik bruto": f"{result['gross_electricity_kwh_day']:,.0f} kWh/hari",
    "Listrik bersih": f"{result['net_electricity_kwh_day']:,.0f} kWh/hari",
    "Pendapatan energi": rupiah_juta(result["energy_revenue_year_rp"]),
    "Pendapatan layanan": rupiah_juta(result["waste_fee_revenue_year_rp"]),
    "Arus kas operasi": rupiah_juta(result["operating_profit_year_rp"]),
}

indicator_df = pd.DataFrame(
    [
        {"Indikator": key, "Nilai": value}
        for key, value in indicators.items()
    ]
)

st.subheader("Tabel indikator lengkap")
st.dataframe(
    indicator_df,
    use_container_width=True,
    hide_index=True,
)

# =========================================================
# 7. ASUMSI MODEL
# =========================================================
with st.expander("Asumsi dan catatan interpretasi"):
    st.markdown("""
    - Bahan layak = sampah masuk × fraksi organik × (1 − reject).
    - VS = bahan layak × TS × VS/TS.
    - Metana = VS × hasil metana × faktor realisasi.
    - Biogas = metana / fraksi metana.
    - Listrik bersih memperhitungkan efisiensi generator dan konsumsi internal.
    - Pendapatan listrik bergantung pada tarif dan energi yang benar-benar terjual.
    - Model perlu divalidasi terhadap data operasional dan penawaran biaya aktual.
    """)

# =========================================================
# 8. SIMPAN KE MYSQL
# =========================================================
if st.button("Simpan simulasi ini", type="primary"):
    record = {
        "waste_tpd": float(waste_tpd),
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

    try:
        save_run(record)
        st.success("Simulasi berhasil disimpan ke MySQL.")
    except Exception as exc:
        st.error(f"Gagal menyimpan simulasi: {exc}")

# =========================================================
# 9. RIWAYAT SIMULASI DARI MYSQL
# =========================================================
st.subheader("Riwayat simulasi tersimpan")

try:
    history = load_runs()

    if history.empty:
        st.caption("Belum ada simulasi tersimpan.")
    else:
        st.dataframe(
            history,
            use_container_width=True,
            hide_index=True,
        )

        csv_data = history.to_csv(index=False).encode("utf-8-sig")
        st.download_button(
            "Unduh riwayat CSV",
            data=csv_data,
            file_name="riwayat_waste_to_energy.csv",
            mime="text/csv",
        )

except Exception as exc:
    st.warning(f"Riwayat belum dapat dimuat: {exc}")

st.caption(f"Status database: {db_status()}")
