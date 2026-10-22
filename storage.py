
import json
import os

import mysql.connector
import pandas as pd
from mysql.connector import Error


def get_connection():
    return mysql.connector.connect(
        host=os.getenv("DB_HOST", "127.0.0.1"),
        port=int(os.getenv("DB_PORT", "3306")),
        user=os.getenv("DB_USER", "root"),
        password=os.getenv("DB_PASSWORD", ""),
        database=os.getenv("DB_NAME", "waste_to_energy"),
        charset="utf8mb4",
    )


def db_status():
    connection = None
    try:
        connection = get_connection()
        return "Terhubung ke MySQL"
    except Error as exc:
        return f"Tidak terhubung: {exc}"
    finally:
        if connection and connection.is_connected():
            connection.close()


def save_run(record):
    connection = get_connection()
    cursor = None

    try:
        cursor = connection.cursor()

        sql = """
            INSERT INTO simulation_history (
                waste_tpd,
                feedstock_tpd,
                methane_nm3_day,
                net_electricity_kwh_day,
                revenue_year_rp,
                opex_year_rp,
                operating_profit_year_rp,
                npv_rp,
                irr,
                details_json
            ) VALUES (
                %s, %s, %s, %s, %s,
                %s, %s, %s, %s, %s
            )
        """

        values = (
            record["waste_tpd"],
            record["feedstock_tpd"],
            record["methane_nm3_day"],
            record["net_electricity_kwh_day"],
            record["revenue_year_rp"],
            record["opex_year_rp"],
            record["operating_profit_year_rp"],
            record["npv_rp"],
            record["irr"],
            json.dumps(
                record.get("indicators", {}),
                ensure_ascii=False,
                allow_nan=False,
            ),
        )

        cursor.execute(sql, values)
        connection.commit()

    except Exception:
        connection.rollback()
        raise

    finally:
        if cursor:
            cursor.close()
        connection.close()


def load_runs():
    connection = get_connection()

    try:
        cursor = connection.cursor(dictionary=True)
        cursor.execute("""
            SELECT
                id,
                created_at,
                waste_tpd,
                feedstock_tpd,
                methane_nm3_day,
                net_electricity_kwh_day,
                revenue_year_rp,
                opex_year_rp,
                operating_profit_year_rp,
                npv_rp,
                irr
            FROM simulation_history
            ORDER BY created_at DESC, id DESC
        """)

        rows = cursor.fetchall()
        return pd.DataFrame(rows)

    finally:
        cursor.close()
        connection.close()
