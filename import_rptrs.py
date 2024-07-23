#!/usr/bin/python3
import os
import requests
import json
import mysql.connector
from mysql.connector import Error
import sys

# Define MySQL connection variables
mysql_host = '127.0.0.1'
mysql_user = 'root'
mysql_password = 'passw0rd'
database_name = 'dmr-database'
table_name = 'rptrs'

# Function to download the JSON file
def download_json(url, filename):
    response = requests.get(url)
    if response.status_code == 200:
        with open(filename, 'wb') as file:
            file.write(response.content)
        print(f'{filename} downloaded successfully.')
    else:
        print(f'Failed to download {filename}.')

# Function to show progress
def show_row_progress(current_row, total_rows):
    progress = current_row / total_rows
    if current_row < total_rows:
        sys.stdout.write(f"\rProcessing... {progress * 100:.2f}% ({current_row}/{total_rows} rows)")
    else:
        sys.stdout.write(f"\rProcessing... 100.00% ({total_rows}/{total_rows} rows)")
    sys.stdout.flush()

# Check if rptrs.json is present, otherwise download it
json_filename = 'rptrs.json'
json_url = 'https://radioid.net/static/rptrs.json'

if not os.path.isfile(json_filename):
    download_json(json_url, json_filename)

# Load JSON data
with open(json_filename, 'r') as file:
    data = json.load(file)

rptrs_data = data['rptrs']

# Connect to MySQL and import data
try:
    connection = mysql.connector.connect(
        host=mysql_host,
        user=mysql_user,
        password=mysql_password,
        database=database_name
    )

    if connection.is_connected():
        cursor = connection.cursor()
        cursor.execute(f"DROP TABLE IF EXISTS {table_name}")
        create_table_query = f"""
        CREATE TABLE {table_name} (
            locator INT PRIMARY KEY,
            id INT,
            callsign VARCHAR(255),
            city VARCHAR(255),
            state VARCHAR(255),
            country VARCHAR(255),
            frequency VARCHAR(255),
            color_code INT,
            `offset` VARCHAR(255),
            assigned VARCHAR(255),
            ts_linked VARCHAR(255),
            trustee VARCHAR(255),
            map_info TEXT,
            map INT,
            ipsc_network VARCHAR(255)
        )
        """
        cursor.execute(create_table_query)
        connection.commit()

        # Insert data and show progress
        insert_query = f"""
        INSERT INTO {table_name} (
            locator, id, callsign, city, state, country, frequency, color_code,
            `offset`, assigned, ts_linked, trustee, map_info, map, ipsc_network
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        
        total_records = len(rptrs_data)
        for idx, record in enumerate(rptrs_data):
            cursor.execute(insert_query, (
                record['locator'], record['id'], record['callsign'], record['city'], record['state'],
                record['country'], record['frequency'], record['color_code'], record['offset'],
                record['assigned'], record['ts_linked'], record['trustee'], record['map_info'],
                record['map'], record['ipsc_network']
            ))
            show_row_progress(idx + 1, total_records)

        connection.commit()
        print(f"\nData imported successfully into {table_name} table.")

except Error as e:
    print(f"Error: {e}")
finally:
    if connection.is_connected():
        cursor.close()
        connection.close()
        print("MySQL connection closed.")

# Remove the JSON file
os.remove(json_filename)
print(f'{json_filename} removed.')
