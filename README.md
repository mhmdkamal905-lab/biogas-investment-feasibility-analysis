WasteIQ


Turning Organic Waste into Energy and Business Opportunities.

WasteIQ is a Python-based Business Intelligence platform designed to analyze the technical potential and financial feasibility of converting organic waste into biogas and renewable energy.

The platform helps users estimate biogas production, electricity generation, operating costs, revenue potential, and investment feasibility through an interactive dashboard. It is designed to support preliminary business planning for waste-to-energy projects using organic waste sourced from landfills and other waste management facilities.

 Project Overview
WasteIQ combines technical simulation and financial analysis to help evaluate waste-to-energy business opportunities.

The system focuses on:
- Estimating the amount of organic waste suitable for processing.
- Simulating methane and biogas production.
- Estimating thermal energy and electricity generation.
- Analyzing operational costs and potential revenue.
- Evaluating investment indicators to support preliminary business decisions.
- Recording simulation results for future review and reporting.

 Key Features

 Waste Potential Analysis
- Daily waste input estimation.
- Organic waste fraction and reject rate analysis.
- Processable feedstock estimation.
- Volatile Solids (VS) calculation for methane production modeling.

 Biogas and Energy Simulation
- Methane production estimation.
- Total biogas volume estimation.
- Thermal energy potential calculation.
- Gross and net electricity generation estimation.
- Process realization and internal energy consumption adjustments.

 Financial Feasibility Analysis
- Capital Expenditure (CAPEX) estimation.
- Operating Expenditure (OPEX) calculation.
- Electricity revenue estimation.
- Waste treatment service revenue modeling.
- Annual operating profit calculation.
- Net Present Value (NPV) analysis.
- Internal Rate of Return (IRR) estimation.
- Simple payback period calculation.

 Business Intelligence Dashboard
- Interactive charts and visualizations.
- Technical and financial performance indicators.
- Scenario-based business simulations.
- Data-driven insights for preliminary investment evaluation.

 Simulation History
- PostgreSQL database integration.
- Simulation result storage and retrieval.
- Historical data review for business evaluation and reporting.

Testing and Validation
- Basic calculation tests.
- Input validation and technical assumption checks.
- Modular technical and financial calculation parameters.

Technology Stack

| Technology | Purpose |
|---|---|
| Python | Core calculation and business logic |
| Pandas | Data processing and analysis |
| Streamlit | Interactive web dashboard |
| Plotly | Interactive data visualization |
| PostgreSQL | Simulation history storage |
| SQLAlchemy | Database connectivity and operations |
| Pytest | Automated testing |

Business Concept

WasteIQ supports a preliminary business model for converting organic waste into renewable energy.

1. Waste Supply: Obtain organic waste from landfills, local governments, or waste management partners.
2. Waste Preparation: Separate unsuitable materials and prepare organic feedstock.
3. Anaerobic Digestion: Process organic material to produce biogas.
4. Energy Conversion: Use biogas to generate electricity or thermal energy.
5. By-product Evaluation: Assess potential digestate utilization based on quality and applicable regulations.
6. Revenue Analysis: Estimate potential income from energy sales, waste treatment services, and other validated revenue streams.
7.Investment Evaluation: Analyze capital requirements, operating costs, projected cash flows, and investment indicators.

Installation

Requirements

- A compatible Python version.
- PostgreSQL for persistent simulation history.
- Git (optional, for cloning the repository).

Run Locally on Windows

Open PowerShell and navigate to the project directory:

powershell
cd waste-to-energy-business-intelligence

py -m venv .venv
.venv\Scripts\Activate.ps1

python -m pip install --upgrade pip
pip install -r requirements.txt

streamlit run app.py

Open the local URL displayed in the terminal to access the dashboard.

PostgreSQL Configuration

1. Create the Database

sql
CREATE DATABASE wte_bi;


2. Initialize the Schema

Run the SQL statements in `database/schema.sql` against the `wte_bi` database.

3. Configure the Database URL

Create a `.env` file based on `.env.example`, or configure the environment variable directly.

Example:

env
DATABASE_URL=postgresql+psycopg://postgres:password@localhost:5432/wte_bi


Replace the username and password with your local PostgreSQL credentials.

To configure the variable for the current PowerShell session:

powershell
$env:DATABASE_URL = "postgresql+psycopg://postgres:password@localhost:5432/wte_bi"

streamlit run app.py


Ensure the application is configured to read `DATABASE_URL` from the environment. A `.env` file is not loaded automatically unless the application explicitly supports it, for example, through `python-dotenv`.

Database configuration is required only for features that persist simulation history. Calculation functionality may be available without PostgreSQL, depending on the application configuration.

 Run Tests

Execute the automated tests with:

powershell
pytest -q


Technical Assumptions and Limitations

- Methane production is estimated from Volatile Solids (VS) mass and the assumed specific methane yield, rather than directly from wet waste mass.
- Process realization represents the assumed effective fraction of the theoretical methane potential.
- Internal energy consumption is deducted when estimating net electricity output.
- Electricity prices and waste treatment fees are user-defined simulation inputs, not guaranteed market rates or contractual revenue.
- NPV, IRR, and payback estimates depend on the financial assumptions and projected cash flows.
- The preliminary model may not include all taxes, financing costs, depreciation, working capital, asset replacement, residual value, environmental compliance costs, or permitting requirements.
- Digestate revenue is not guaranteed and requires validation of product quality, market demand, and applicable regulations.

Important: WasteIQ is intended for preliminary screening and business planning. It does not replace a detailed engineering study, laboratory testing, environmental assessment, market research, or a comprehensive investment feasibility study.

Real-world project validation should include waste characterization, biochemical methane potential (BMP) testing, vendor quotations, waste supply agreements, energy purchase arrangements, and relevant regulatory requirements.

Future Development

Potential future improvements include:

- Integration with real-world landfill waste data.
- Financial sensitivity analysis for energy prices, operating costs, and processing capacity.
- Comparison of project locations and facility scales.
- Operational performance monitoring.
- Monthly report generation and PDF export.
- Environmental impact and emissions reduction analysis.
- Integration of actual operating data to improve business projections.

License

This project is intended to use the MIT License. Add a `LICENSE` file containing the appropriate license text before distributing the project publicly.

Author

Developed as a technology and business analytics project focused on renewable energy, waste management, and sustainable business development.

*asteIQ — Turning Organic Waste into Business Opportunities.**
