#!/usr/bin/python3
import argparse
import pandas as pd
import requests
import os
import mysql.connector
from mysql.connector import Error
import csv
import time
from datetime import datetime

# Define MySQL connection variables
mysql_host = '127.0.0.1'
mysql_user = 'root'
mysql_password = 'passw0rd'
database_name = 'dmr-database'

# URL to download the RadioID CSV file
radioid_url = 'https://radioid.net/static/user.csv'
city_state_nl_csv_url = 'https://raw.githubusercontent.com/DMR-Database/dmr-database-frontend/main/citys_nl.csv'
city_state_de_csv_url = 'https://raw.githubusercontent.com/DMR-Database/dmr-database-frontend/main/citys_de.csv'
user_ext_csv_url = 'https://raw.githubusercontent.com/DMR-Database/dmr-database-frontend/main/radioid_ext.csv'

# Path to city_state mapping CSV
city_state_nl_csv = 'citys_nl.csv'
city_state_de_csv = 'citys_de.csv'
radioid_filename = 'user.csv'

# Path to users_ext.csv for merging
ext_filename = 'radioid__ext.csv'

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

def check_files_csv():
    try:
        print(f"Check if needed files are present...")
        # Check if the necessary files exist
        if not os.path.exists(city_state_nl_csv):
            print(f"{ext_filename} not found. Downloading it first.")
            download_user_csv(user_ext_csv_url, ext_filename)
        print(f"Found : {ext_filename}...")

        # Check if the necessary files exist
        if not os.path.exists(city_state_nl_csv):
            print(f"{city_state_nl_csv} not found. Downloading it first.")
            download_user_csv(city_state_nl_csv_url, city_state_nl_csv)
        print(f"Found : {city_state_nl_csv}...")

        # Check if the necessary files exist
        if not os.path.exists(city_state_de_csv):
            print(f"{city_state_de_csv} not found. Downloading it first.")
            download_user_csv(city_state_de_csv_url, city_state_de_csv)
        print(f"Found : {city_state_de_csv}...")

        # Check if the necessary files exist
        if not os.path.exists(radioid_filename):
            print(f"{radioid_filename} not found. Downloading it first.")
            download_user_csv(radioid_url, radioid_filename)
        print(f"Found : {radioid_filename}...")
        print("================================")

    except Exception as e:
        print(f"Error merging CSV files: {e}")

def download_user_csv(csv_url, csv_name):
    # Downloads user.csv from URL.
    try:
        print(f"Downloading {csv_name}")
        response = requests.get(csv_url)
        response.raise_for_status()  # Raise an exception for HTTP errors

        with open(csv_name, 'wb') as f:
            f.write(response.content)

        print(f"Download complete: {csv_name}")
    except Exception as e:
        print(f"Error downloading {csv_name}: {e}")

def merge_csv(csv_filename, ext_filename):
    # Merge users_ext.csv into user.csv, overwriting data in user.csv.
    try:
        print(f"Merging {ext_filename} into {csv_filename}...")
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


def fill_empty_state_nl(csv_filename, city_state_nl_csv):
    # Fills empty STATE values in user.csv from city_state_nl_csv.
    initial_line_count = 0
    final_line_count = 0
    
    try:
        print(f"Starting filling States from {city_state_nl_csv}")

        # Check if the necessary files exist
        if not os.path.exists(csv_filename):
            print(f"Error: {csv_filename} not found.")
            return initial_line_count, final_line_count
        if not os.path.exists(city_state_nl_csv):
            print(f"Error: {city_state_nl_csv} not found.")
            return initial_line_count, final_line_count

        # Load city-state mapping from city_state_nl_csv into a dictionary
        city_state_map = {}
        with open(city_state_nl_csv, 'r', newline='', encoding='utf-8') as city_file:
            city_reader = csv.DictReader(city_file)

            if 'CITY' not in city_reader.fieldnames or 'STATE' not in city_reader.fieldnames:
                print(f"Error: Expected headers 'CITY' and 'STATE' not found in {city_state_nl_csv}")
                return initial_line_count, final_line_count

            for row in city_reader:
                city_state_map[row['CITY'].strip().lower()] = row['STATE']

        # Count lines in user.csv before processing
        with open(csv_filename, 'r', newline='', encoding='utf-8') as user_file:
            initial_line_count = sum(1 for line in user_file)

        # Read user.csv and update the STATE where it is empty
        updated_rows = []
        with open(csv_filename, 'r', newline='', encoding='utf-8') as user_file:
            user_reader = csv.DictReader(user_file)
            fieldnames = user_reader.fieldnames

            if 'CITY' not in fieldnames or 'STATE' not in fieldnames:
                print(f"Error: Expected headers 'CITY' and 'STATE' not found in {csv_filename}")
                return initial_line_count, final_line_count

            total_rows = sum(1 for row in user_reader)  # Count total rows in user.csv

            # Reset the reader to start from the beginning
            user_file.seek(0)
            next(user_reader)  # Skip header row

            current_row = 0
            for row in user_reader:
                current_row += 1
                if row['STATE'] == '' and row['RADIO_ID'].startswith(('204')):
                    city = row['CITY'].strip().lower()  # Normalize city name to lowercase
                    if city in city_state_map:
                        # Create a copy of the row before modifying it
                        updated_row = row.copy()
                        updated_row['STATE'] = city_state_map[city]
                        updated_rows.append(updated_row)
                        show_row_progress(current_row, total_rows, updated_row['RADIO_ID'], updated_row['CALLSIGN'])
                    else:
                        updated_rows.append(row)  # No state found, append original row
                        show_row_progress(current_row, total_rows, row['RADIO_ID'], row['CALLSIGN'])
                else:
                    updated_rows.append(row)
                    show_row_progress(current_row, total_rows, row['RADIO_ID'], row['CALLSIGN'])

        # Count lines in user.csv after processing
        final_line_count = len(updated_rows)

        # Write the updated data back to user.csv
        with open(csv_filename, 'w', newline='', encoding='utf-8') as user_file:
            user_writer = csv.DictWriter(user_file, fieldnames=fieldnames)
            user_writer.writeheader()
            user_writer.writerows(updated_rows)

        print(f"\nCompleted updating {csv_filename} from {city_state_nl_csv}")

    except Exception as e:
        print(f"Error filling empty states: {e}")

    return initial_line_count, final_line_count

# Example usage:
#initial_count, final_count = fill_empty_state('user.csv', 'city_state.csv')
#print(f"Initial line count in user.csv: {initial_count}")
#print(f"Final line count in user.csv after processing: {final_count}")

def fill_empty_state_de(csv_filename, city_state_de_csv):
    # Fills empty STATE values in user.csv from city_state_de_csv.
    initial_line_count = 0
    final_line_count = 0
    
    try:
        print(f"Starting filling States from {city_state_de_csv}")

        # Check if the necessary files exist
        if not os.path.exists(csv_filename):
            print(f"Error: {csv_filename} not found.")
            return initial_line_count, final_line_count
        if not os.path.exists(city_state_de_csv):
            print(f"Error: {city_state_de_csv} not found.")
            return initial_line_count, final_line_count

        # Load city-state mapping from city_state_de_csv into a dictionary
        city_state_map = {}
        with open(city_state_de_csv, 'r', newline='', encoding='utf-8') as city_file:
            city_reader = csv.DictReader(city_file)

            if 'CITY' not in city_reader.fieldnames or 'STATE' not in city_reader.fieldnames:
                print(f"Error: Expected headers 'CITY' and 'STATE' not found in {city_state_de_csv}")
                return initial_line_count, final_line_count

            for row in city_reader:
                city_state_map[row['CITY'].strip().lower()] = row['STATE']

        # Count lines in user.csv before processing
        with open(csv_filename, 'r', newline='', encoding='utf-8') as user_file:
            initial_line_count = sum(1 for line in user_file)

        # Read user.csv and update the STATE where it is empty
        updated_rows = []
        with open(csv_filename, 'r', newline='', encoding='utf-8') as user_file:
            user_reader = csv.DictReader(user_file)
            fieldnames = user_reader.fieldnames

            if 'CITY' not in fieldnames or 'STATE' not in fieldnames:
                print(f"Error: Expected headers 'CITY' and 'STATE' not found in {csv_filename}")
                return initial_line_count, final_line_count

            total_rows = sum(1 for row in user_reader)  # Count total rows in user.csv

            # Reset the reader to start from the beginning
            user_file.seek(0)
            next(user_reader)  # Skip header row

            current_row = 0
            for row in user_reader:
                current_row += 1
                if row['STATE'] == '' and row['RADIO_ID'].startswith(('262', '263', '264', '265')):
                    city = row['CITY'].strip().lower()  # Normalize city name to lowercase
                    if city in city_state_map:
                        # Create a copy of the row before modifying it
                        updated_row = row.copy()
                        updated_row['STATE'] = city_state_map[city]
                        updated_rows.append(updated_row)
                        show_row_progress(current_row, total_rows, updated_row['RADIO_ID'], updated_row['CALLSIGN'])
                    else:
                        updated_rows.append(row)  # No state found, append original row
                        show_row_progress(current_row, total_rows, row['RADIO_ID'], row['CALLSIGN'])
                else:
                    updated_rows.append(row)
                    show_row_progress(current_row, total_rows, row['RADIO_ID'], row['CALLSIGN'])

        # Count lines in user.csv after processing
        final_line_count = len(updated_rows)

        # Write the updated data back to user.csv
        with open(csv_filename, 'w', newline='', encoding='utf-8') as user_file:
            user_writer = csv.DictWriter(user_file, fieldnames=fieldnames)
            user_writer.writeheader()
            user_writer.writerows(updated_rows)

        print(f"\nCompleted updating {csv_filename} from {city_state_de_csv}")

    except Exception as e:
        print(f"Error filling empty states: {e}")

    return initial_line_count, final_line_count

# Example usage:
#initial_count, final_count = fill_empty_state('user.csv', 'city_state.csv')
#print(f"Initial line count in user.csv: {initial_count}")
#print(f"Final line count in user.csv after processing: {final_count}")

def show_row_progress(current_row, total_rows, radio_id, callsign):
    progress_percent = current_row / total_rows * 100
    print(f"\rProcessed {current_row}/{total_rows} rows - RADIO_ID: {radio_id}, CALLSIGN: {callsign} ({progress_percent:.2f}%) ", end='')


def import_radio_id_from_file(conn, csv_filename):
    # Imports RadioID data from user.csv into MySQL.
    try:
        print(f"RADIOID data import started from {csv_filename}")

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

def main(version):
    start_time = time.time()

    # Parse command-line arguments
    parser = argparse.ArgumentParser(description='DMR Database Script')
    parser.add_argument('-r', action='store_true', dest='radioid', help='Load RadioID data')
    parser.add_argument('-v', action='version', version=f'%(prog)s {version}')
    args = parser.parse_args()

    if not any(vars(args).values()):
        args.radioid = True

    # Check if -r flag is set to load RadioID data
    if args.radioid:
        now = datetime.now()
        current_time = now.strftime("%H:%M:%S")
        print("================================")
        print("Start processing RadioID data...")
        print("Starting process at:", current_time)
        print("================================")        
    else:
        parser.print_help()
        return
    # Delete the user.csv file
    try:
        os.remove('user.csv')
        print("Removed old user.csv file")
    except OSError as e:
        print(f"No old user csv found: {e}")

    #Check if needed files are present
    check_files_csv()
    # Merge users_ext.csv into user.csv
    merge_csv('user.csv', ext_filename)
    print("================================")           

    # Fill empty STATE values in user.csv from city_state_nl_csv
    fill_empty_state_nl('user.csv', city_state_nl_csv)
    print("================================")           
    fill_empty_state_de('user.csv', city_state_de_csv)
    print("================================")           


    # Connect to MySQL
    print(f"Connecting to SQL on {mysql_host}")
    conn = connect_mysql()
    if conn is None:
        return
    
    # Import RadioID data from user.csv into MySQL
    total_imported = import_radio_id_from_file(conn, 'user.csv')

    # Copy data to new tables
    #copy_data_to_new_tables(conn)

    # Close MySQL connection
    conn.close()
    print("MySQL connection closed")
    print("================================")

    # Calculate and print total script execution time
    end_time = time.time()
    execution_time = end_time - start_time
    #print(f"Total execution time: {execution_time:.2f} seconds")
    print("Process completed at:", datetime.now().strftime("%H:%M:%S"))
    print("Time taken:", round(execution_time, 2), "seconds (", round(execution_time / 60, 2), "minutes)")
    
    # Delete the user.csv file
#    try:
#        os.remove('user.csv')
#        print("user.csv file deleted")
#    except OSError as e:
#        print(f"Error deleting user.csv file: {e}")
        
if __name__ == "__main__":
    main(script_version)
