CREATE TABLE IF NOT EXISTS scenario_runs (
    id BIGSERIAL PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    waste_tpd DOUBLE PRECISION NOT NULL CHECK (waste_tpd > 0),
    feedstock_tpd DOUBLE PRECISION NOT NULL CHECK (feedstock_tpd >= 0),
    methane_nm3_day DOUBLE PRECISION NOT NULL CHECK (methane_nm3_day >= 0),
    net_electricity_kwh_day DOUBLE PRECISION NOT NULL CHECK (net_electricity_kwh_day >= 0),
    revenue_year_rp DOUBLE PRECISION NOT NULL CHECK (revenue_year_rp >= 0),
    opex_year_rp DOUBLE PRECISION NOT NULL CHECK (opex_year_rp >= 0),
    operating_profit_year_rp DOUBLE PRECISION NOT NULL,
    npv_rp DOUBLE PRECISION NOT NULL,
    irr DOUBLE PRECISION
);
