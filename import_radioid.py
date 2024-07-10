#!/usr/bin/python3
import argparse
import pandas as pd
import requests
import os
import mysql.connector
from mysql.connector import Error
import csv
import time

# Define MySQL connection variables
mysql_host = '127.0.0.1'
mysql_user = 'root'
mysql_password = 'passw0rd'
database_name = 'dmr-database'

# URL to download the RadioID CSV file
radioid_url = 'https://radioid.net/static/user.csv'

# Path to city_state mapping CSV
city_state_csv = 'citys.csv'

# Path to users_ext.csv for merging
ext_filename = 'users_ext.csv'

# Define script version
script_version = '1.2'

def connect_mysql():
    # Establishes a connection to MySQL and returns the connection object.
    try:
        conn = mysql.connector.connect(
            host=mysql_host,
            user=mysql_user,
            password=mysql_password,
            database=database_name,
            auth_plugin='mysql_native_password'
        )
        print("Connected to MySQL database")
        return conn
    except Error as e:
        print(f"Error connecting to MySQL: {e}")
        return None

def download_user_csv(csv_url):
    # Downloads user.csv from URL.
    try:
        print("Downloading user.csv")
        response = requests.get(csv_url)
        response.raise_for_status()  # Raise an exception for HTTP errors

        with open('user.csv', 'wb') as f:
            f.write(response.content)

        print("Download complete: user.csv")
    except Exception as e:
        print(f"Error downloading user.csv: {e}")

def merge_csv(csv_filename, ext_filename):
    # Merge users_ext.csv into user.csv, overwriting data in user.csv.
    try:
        print(f"Merging {ext_filename} into {csv_filename}...")
        
        # Check if the necessary files exist
        if not os.path.exists(csv_filename):
            print(f"{csv_filename} not found. Downloading it first.")
            download_user_csv(radioid_url)

        if os.path.exists(csv_filename) and os.path.exists(ext_filename):
            # Read user.csv into a dictionary keyed by RADIO_ID
            user_data = {}
            with open(csv_filename, 'r', newline='', encoding='utf-8') as user_file:
                user_reader = csv.DictReader(user_file)
                for row in user_reader:
                    user_data[row['RADIO_ID']] = row

            # Read users_ext.csv and overwrite user.csv data with it
            merge_count = 0
            with open(ext_filename, 'r', newline='', encoding='utf-8') as ext_file:
                ext_reader = csv.DictReader(ext_file)
                for row in ext_reader:
                    user_data[row['RADIO_ID']] = row
                    merge_count += 1

            # Write the merged data back to user.csv
            with open(csv_filename, 'w', newline='', encoding='utf-8') as user_file:
                fieldnames = ext_reader.fieldnames  # Use the fieldnames from the extension file
                user_writer = csv.DictWriter(user_file, fieldnames=fieldnames)
                user_writer.writeheader()
                for row in user_data.values():
                    user_writer.writerow(row)

            print(f"Merged {merge_count} lines from {ext_filename} into {csv_filename}.")
            print(f"Completed updating {csv_filename} from {ext_filename}")

        else:
            if not os.path.exists(csv_filename):
                print(f"Failed to merge {ext_filename} into {csv_filename}: {csv_filename} not found.")
            if not os.path.exists(ext_filename):
                print(f"Failed to merge {ext_filename} into {csv_filename}: {ext_filename} not found.")

    except Exception as e:
        print(f"Error merging CSV files: {e}")

def fill_empty_state(csv_filename, city_state_csv):
    # Fills empty STATE values in user.csv from city_state_csv.
    try:
        print(f"Starting filling States from {city_state_csv}")

        # Check if the necessary files exist
        if not os.path.exists(csv_filename):
            print(f"Error: {csv_filename} not found.")
            return
        if not os.path.exists(city_state_csv):
            print(f"Error: {city_state_csv} not found.")
            return

        # Load city-state mapping from city_state_csv into a dictionary
        city_state_map = {}
        with open(city_state_csv, 'r', newline='', encoding='utf-8') as city_file:
            city_reader = csv.DictReader(city_file)

            if 'CITY' not in city_reader.fieldnames or 'STATE' not in city_reader.fieldnames:
                print(f"Error: Expected headers 'CITY' and 'STATE' not found in {city_state_csv}")
                return

            for row in city_reader:
                city_state_map[row['CITY'].strip().lower()] = row['STATE']

        # Read user.csv and update the STATE where it is empty
        updated_rows = []
        with open(csv_filename, 'r', newline='', encoding='utf-8') as user_file:
            user_reader = csv.DictReader(user_file)
            fieldnames = user_reader.fieldnames

            if 'CITY' not in fieldnames or 'STATE' not in fieldnames:
                print(f"Error: Expected headers 'CITY' and 'STATE' not found in {csv_filename}")
                return

            total_rows = sum(1 for row in user_reader)  # Count total rows in user.csv

            # Reset the reader to start from the beginning
            user_file.seek(0)
            next(user_reader)  # Skip header row

            current_row = 0
            for row in user_reader:
                current_row += 1
                if row['STATE'] == '' and row['CALLSIGN'].startswith(('PA', 'PB', 'PC', 'PD', 'PE', 'PF', 'PG', 'PH', 'PI')):
                    city = row['CITY'].strip().lower()  # Normalize city name to lowercase
                    if city in city_state_map:
                        row['STATE'] = city_state_map[city]
                        updated_rows.append(row)
                        show_row_progress(current_row, total_rows, row['RADIO_ID'], row['CALLSIGN'])
                else:
                    updated_rows.append(row)
                    show_row_progress(current_row, total_rows, row['RADIO_ID'], row['CALLSIGN'])

        # Write the updated data back to user.csv
        with open(csv_filename, 'w', newline='', encoding='utf-8') as user_file:
            user_writer = csv.DictWriter(user_file, fieldnames=fieldnames)
            user_writer.writeheader()
            user_writer.writerows(updated_rows)

        print(f"\nCompleted updating {csv_filename} from {city_state_csv}")

    except Exception as e:
        print(f"Error filling empty states: {e}")

def show_row_progress(current_row, total_rows, radio_id, callsign):
    progress_percent = current_row / total_rows * 100
    print(f"\rProcessed {current_row}/{total_rows} rows - RADIO_ID: {radio_id}, CALLSIGN: {callsign} ({progress_percent:.2f}%)", end='')

def import_radio_id_from_file(conn, csv_filename):
    # Imports RadioID data from user.csv into MySQL.
    try:
        print("RADIOID data import started from file")

        # Read CSV into DataFrame
        df = pd.read_csv(csv_filename)

        # Check if all required columns are present in the DataFrame
        required_columns = ['RADIO_ID', 'CALLSIGN', 'FIRST_NAME', 'LAST_NAME', 'CITY', 'STATE', 'COUNTRY']
        for col in required_columns:
            if col not in df.columns:
                raise ValueError(f"Column '{col}' not found in CSV data")

        # Replace NaN values with empty strings
        df = df.fillna('')

        # Create new table and import data
        cursor = conn.cursor()
        cursor.execute("DROP TABLE IF EXISTS radioid_data;")
        create_table_query = """
            CREATE TABLE radioid_data (
                RADIO_ID INT,
                CALLSIGN TEXT,
                FIRST_NAME TEXT,
                LAST_NAME TEXT,
                CITY TEXT,
                STATE TEXT,
                COUNTRY TEXT
            );
        """
        cursor.execute(create_table_query)

        total_rows = len(df)
        current_row = 0
        for index, row in df.iterrows():
            insert_query = """
                INSERT INTO radioid_data (RADIO_ID, CALLSIGN, FIRST_NAME, LAST_NAME, CITY, STATE, COUNTRY) 
                VALUES (%s, %s, %s, %s, %s, %s, %s);
            """
            cursor.execute(insert_query, (
                int(row['RADIO_ID']), str(row['CALLSIGN']), str(row['FIRST_NAME']), str(row['LAST_NAME']),
                str(row['CITY']), str(row['STATE']), str(row['COUNTRY'])
            ))
            current_row += 1
            print(f"\rImported {current_row}/{total_rows} rows into RadioID database ({current_row / total_rows * 100:.2f}%)", end='')

        conn.commit()
        print("\nRadioID data imported into MySQL successfully")
        return total_rows
    except Exception as e:
        print(f"Error importing RadioID data into MySQL: {e}")
        return 0

def copy_data_to_new_tables(conn):
    try:
        cursor = conn.cursor()

        # Drop existing tables if they exist
        tables = ['userat', 'usermd2017', 'userbin', 'usrbin', 'pistar']
        for table in tables:
            cursor.execute(f"DROP TABLE IF EXISTS {table};")

        # Create new tables
        create_tables_query = """
            CREATE TABLE userat LIKE radioid_data;
            CREATE TABLE usermd2017 LIKE radioid_data;
            CREATE TABLE userbin LIKE radioid_data;
            CREATE TABLE usrbin LIKE radioid_data;
            CREATE TABLE pistar LIKE radioid_data;
        """
        cursor.execute(create_tables_query)

        # Copy data from radioid_data to new tables
        copy_data_query = """
            INSERT INTO userat SELECT * FROM radioid_data;
            INSERT INTO usermd2017 SELECT * FROM radioid_data;
            INSERT INTO userbin SELECT * FROM radioid_data;
            INSERT INTO usrbin SELECT * FROM radioid_data;
            INSERT INTO pistar SELECT * FROM radioid_data;
        """
        cursor.execute(copy_data_query)

        conn.commit()
        print("Data copied to new tables successfully")

    except Exception as e:
        print(f"Error copying data to new tables: {e}")

def main(version):
    start_time = time.time()

    # Parse command-line arguments
    parser = argparse.ArgumentParser(description='DMR Database Script')
    parser.add_argument('-r', action='store_true', dest='radioid', help='Load RadioID data')
    parser.add_argument('-v', action='version', version=f'%(prog)s {version}')
    args = parser.parse_args()

    # Check if -r flag is set to load RadioID data
    if args.radioid:
        print("Loading RadioID data...")
    else:
        parser.print_help()
        return

    # Connect to MySQL
    conn = connect_mysql()
    if conn is None:
        return

    # Step 1: Merge users_ext.csv into user.csv
    merge_csv('user.csv', ext_filename)

    # Step 2: Fill empty STATE values in user.csv from city_state_csv
    fill_empty_state('user.csv', city_state_csv)

    # Step 3: Import RadioID data from user.csv into MySQL
    total_imported = import_radio_id_from_file(conn, 'user.csv')

    # Step 4: Copy data to new tables
    #copy_data_to_new_tables(conn)

    # Calculate and print total script execution time
    end_time = time.time()
    execution_time = end_time - start_time
    print(f"Total execution time: {execution_time:.2f} seconds")

    # Close MySQL connection
    conn.close()
    print("MySQL connection closed")

if __name__ == "__main__":
    main(script_version)

