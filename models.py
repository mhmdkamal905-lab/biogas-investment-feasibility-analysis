from dataclasses import dataclass
from typing import Optional
import math


@dataclass
class TechnicalInputs:
    waste_tpd: float
    organic_fraction: float
    reject_fraction: float
    ts_fraction: float
    vs_fraction_of_ts: float
    methane_yield_nm3_per_kg_vs: float
    process_realization: float
    methane_fraction: float
    operating_days: int
    methane_lhv_kwh_nm3: float
    generator_efficiency: float
    parasitic_energy_fraction: float


@dataclass
class FinancialInputs:
    capex_rp: float
    fixed_opex_year_rp: float
    variable_cost_rp_per_kg: float
    electricity_price_rp_per_kwh: float
    waste_service_fee_rp_per_kg: float
    other_revenue_year_rp: float
    discount_rate: float
    project_years: int


def _check_fraction(name, value, allow_zero=True):
    low = 0 if allow_zero else 1e-12
    if not (low <= value <= 1):
        raise ValueError(f"{name} harus berada pada rentang {low} sampai 1.")


def validate_inputs(t: TechnicalInputs, f: FinancialInputs):
    if t.waste_tpd <= 0:
        raise ValueError("Sampah masuk harus lebih besar dari nol.")
    for name in ("organic_fraction", "reject_fraction", "ts_fraction",
                 "vs_fraction_of_ts", "process_realization", "methane_fraction",
                 "generator_efficiency", "parasitic_energy_fraction"):
        _check_fraction(name, getattr(t, name))
    if t.methane_fraction <= 0:
        raise ValueError("Fraksi metana harus lebih besar dari nol.")
    if t.methane_yield_nm3_per_kg_vs < 0 or t.methane_lhv_kwh_nm3 <= 0:
        raise ValueError("Hasil metana tidak boleh negatif dan nilai kalor harus positif.")
    if not 1 <= t.operating_days <= 365:
        raise ValueError("Hari operasi harus antara 1 dan 365.")
    for name in ("capex_rp", "fixed_opex_year_rp", "variable_cost_rp_per_kg",
                 "electricity_price_rp_per_kwh", "waste_service_fee_rp_per_kg",
                 "other_revenue_year_rp"):
        if getattr(f, name) < 0:
            raise ValueError(f"{name} tidak boleh negatif.")
    if not 0 < f.discount_rate < 1:
        raise ValueError("Tingkat diskonto harus lebih besar dari 0 dan kurang dari 100%.")
    if f.project_years < 1:
        raise ValueError("Umur proyek minimal 1 tahun.")


def _npv(rate: float, cashflows: list[float]) -> float:
    return sum(cf / ((1 + rate) ** year) for year, cf in enumerate(cashflows))


def _irr(cashflows: list[float]) -> Optional[float]:
    # Bisection supports the conventional case: initial negative investment followed by cash inflows.
    if not cashflows or cashflows[0] >= 0 or not any(x > 0 for x in cashflows[1:]):
        return None
    lo, hi = -0.99, 5.0
    flo, fhi = _npv(lo, cashflows), _npv(hi, cashflows)
    if flo * fhi > 0:
        return None
    for _ in range(200):
        mid = (lo + hi) / 2
        fm = _npv(mid, cashflows)
        if abs(fm) < 1e-7:
            return mid
        if flo * fm <= 0:
            hi, fhi = mid, fm
        else:
            lo, flo = mid, fm
    return (lo + hi) / 2


def run_model(t: TechnicalInputs, f: FinancialInputs) -> dict:
    validate_inputs(t, f)

    incoming_kg_day = t.waste_tpd * 1000
    organic_kg_day = incoming_kg_day * t.organic_fraction
    feedstock_kg_day = organic_kg_day * (1 - t.reject_fraction)
    vs_kg_day = feedstock_kg_day * t.ts_fraction * t.vs_fraction_of_ts

    methane_nm3_day = vs_kg_day * t.methane_yield_nm3_per_kg_vs * t.process_realization
    biogas_nm3_day = methane_nm3_day / t.methane_fraction
    thermal_kwh_day = methane_nm3_day * t.methane_lhv_kwh_nm3
    gross_electricity_kwh_day = thermal_kwh_day * t.generator_efficiency
    net_electricity_kwh_day = gross_electricity_kwh_day * (1 - t.parasitic_energy_fraction)

    annual_net_kwh = net_electricity_kwh_day * t.operating_days
    feedstock_kg_year = feedstock_kg_day * t.operating_days
    energy_revenue_year = annual_net_kwh * f.electricity_price_rp_per_kwh
    waste_fee_revenue_year = feedstock_kg_year * f.waste_service_fee_rp_per_kg
    revenue_year = energy_revenue_year + waste_fee_revenue_year + f.other_revenue_year_rp
    variable_opex_year = feedstock_kg_year * f.variable_cost_rp_per_kg
    opex_year = f.fixed_opex_year_rp + variable_opex_year
    operating_profit_year = revenue_year - opex_year

    cashflows = [-f.capex_rp] + [operating_profit_year] * f.project_years
    npv = _npv(f.discount_rate, cashflows)
    irr = _irr(cashflows)
    simple_payback = f.capex_rp / operating_profit_year if operating_profit_year > 0 else None

    cumulative = -f.capex_rp
    cashflow_rows = [{"year": 0, "cashflow_rp": -f.capex_rp, "cumulative_cashflow_rp": cumulative}]
    for year in range(1, f.project_years + 1):
        cumulative += operating_profit_year
        cashflow_rows.append({"year": year, "cashflow_rp": operating_profit_year,
                              "cumulative_cashflow_rp": cumulative})

    return {
        "incoming_tpd": t.waste_tpd, "organic_tpd": organic_kg_day / 1000,
        "feedstock_tpd": feedstock_kg_day / 1000, "vs_kg_day": vs_kg_day,
        "methane_nm3_day": methane_nm3_day, "biogas_nm3_day": biogas_nm3_day,
        "thermal_kwh_day": thermal_kwh_day,
        "gross_electricity_kwh_day": gross_electricity_kwh_day,
        "net_electricity_kwh_day": net_electricity_kwh_day,
        "energy_revenue_year_rp": energy_revenue_year,
        "waste_fee_revenue_year_rp": waste_fee_revenue_year,
        "revenue_year_rp": revenue_year, "opex_year_rp": opex_year,
        "operating_profit_year_rp": operating_profit_year, "npv_rp": npv,
        "irr": irr, "simple_payback_years": simple_payback, "cashflow": cashflow_rows,
    }
