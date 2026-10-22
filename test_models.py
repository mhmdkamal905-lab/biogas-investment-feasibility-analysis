import pytest
from src.models import TechnicalInputs, FinancialInputs, run_model

def inputs():
    t = TechnicalInputs(
        waste_tpd=5, organic_fraction=.60, reject_fraction=.15,
        ts_fraction=.20, vs_fraction_of_ts=.80,
        methane_yield_nm3_per_kg_vs=.25, process_realization=.75,
        methane_fraction=.60, operating_days=330, methane_lhv_kwh_nm3=9.94,
        generator_efficiency=.35, parasitic_energy_fraction=.12
    )
    f = FinancialInputs(
        capex_rp=2_500_000_000, fixed_opex_year_rp=250_000_000,
        variable_cost_rp_per_kg=150, electricity_price_rp_per_kwh=1200,
        waste_service_fee_rp_per_kg=0, other_revenue_year_rp=0,
        discount_rate=.12, project_years=15
    )
    return t, f

def test_mass_balance_feedstock():
    t, f = inputs()
    r = run_model(t, f)
    assert r["feedstock_tpd"] == pytest.approx(5 * .60 * .85)

def test_vs_is_below_wet_feedstock():
    t, f = inputs()
    r = run_model(t, f)
    assert r["vs_kg_day"] < r["feedstock_tpd"] * 1000

def test_energy_net_not_above_gross():
    t, f = inputs()
    r = run_model(t, f)
    assert r["net_electricity_kwh_day"] <= r["gross_electricity_kwh_day"]

def test_negative_waste_rejected():
    t, f = inputs()
    t.waste_tpd = -1
    with pytest.raises(ValueError):
        run_model(t, f)
