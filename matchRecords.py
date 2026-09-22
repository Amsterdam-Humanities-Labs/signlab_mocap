#!/usr/bin/env python3

from db_credentials import DB_PASSWORD
import os
import shutil
import subprocess
import json
import re
from pathlib import Path
from datetime import datetime
import pytz
import mysql.connector
from mysql.connector import errorcode
import sys
from sc_paths import sc_path
# The heartbeat client. Prefer the installed signlab-client-monitor package,
# and fall back to the copy in pythonCron's checkout - which is what this line
# has always done, and what still happens on any host where the package has
# not been installed. The fallback is the reason this script is coupled to
# another repository's location on one particular server; installing the
# package is what removes that coupling.
try:
    from signlab_client_monitor import ClientMonitor
except ImportError:
    sys.path.insert(0, '/home/gomer/pythonCron')
    from python_client import ClientMonitor

# Initialize Client Monitor
monitor = ClientMonitor(
    api_url="https://signcollect.nl/client_monitor_api/api.php",
    client_id="match-livelink-mocap",
    client_name="Match LiveLink with Mocap",
    description="Matches LiveLink JSON files with mocap FBX files",
    heartbeat_interval=86400  # Daily at 22:30
)

# -----------------------------
# Configuration
# -----------------------------

# Directories
JSON_DIRECTORY = Path(sc_path("media", "llVideos"))      # Replace with your JSON files directory
FBX_DIRECTORY = Path(sc_path("media_fbx"))              # Replace with your FBX files directory

# MySQL Database Configuration
DB_CONFIG = {
    'user': 'user',
    'password': DB_PASSWORD,
    'host': 'localhost',
    'database': 'admin_gebarenoverleg',
    'raise_on_warnings': True
}

# Regular Expressions
# Regex for JSON filenames
JSON_FILENAME_REGEX = re.compile(
    r'^(?P<date>\d{8})_(?P<gloss>[^_]+)_(\d{6})_(?P<number>\d+)_(\d+)\.json$'
)

# Regex for FBX filenames (assuming they follow the same pattern as MP4)
FBX_FILENAME_REGEX = re.compile(
    r'^(?P<date>\d{8})_(?P<gloss>[^_]+)_(\d{6})_(?P<number>\d+)_(\d+)\.fbx$'
)

# Cutoff date: December 7, 2024
CUTOFF_DATE = datetime(2024, 12, 5)

# -----------------------------
# Function Definitions
# -----------------------------

def connect_to_database(config):
    """
    Establishes a connection to the MySQL database.
    """
    try:
        cnx = mysql.connector.connect(**config)
        print("    Successfully connected to the database.")
        return cnx
    except mysql.connector.Error as err:
        if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
            print("    Error: Incorrect username or password.")
        elif err.errno == errorcode.ER_BAD_DB_ERROR:
            print("    Error: Database does not exist.")
        else:
            print(f"    Error: {err}")
        return None

def parse_json_fields(json_file):
    """
    Parses the 'date', 'gloss', and 'number' fields from the JSON file's filename.
    Returns a tuple (file_date, gloss, number) or (None, None, None) if parsing fails or date is before cutoff.
    """
    try:
        match = JSON_FILENAME_REGEX.match(json_file.name)
        if not match:
            print(f"    Warning: Filename does not match expected format: {json_file.name}. Skipping.")
            return (None, None, None)
        
        # Extract date, gloss, and number from the filename
        date_str = match.group('date')  # e.g., '20241206'
        gloss = match.group('gloss')     # e.g., 'ZWITSERLAND-C'
        number = match.group('number')   # e.g., '0'

        # Convert date string to datetime object
        file_date = datetime.strptime(date_str, '%Y%m%d')

        # Compare with cutoff date
        if file_date < CUTOFF_DATE:
            # print(f"    Skipping file {json_file.name} as its date {file_date.strftime('%Y-%m-%d')} is before the cutoff date.")
            return (None, None, None)

        return (file_date, gloss, number)
    except Exception as e:
        print(f"    Error parsing {json_file.name}: {e}. Skipping.")
        return (None, None, None)

def find_matching_fbx(gloss, number):
    """
    Searches for an FBX file in FBX_DIRECTORY that matches the given gloss and number.
    Returns the Path of the matching FBX file or None if not found.
    """
    matching_fbx = None
    for fbx in sorted(FBX_DIRECTORY.glob("*.fbx")):
        match = FBX_FILENAME_REGEX.match(fbx.name)
        if not match:
            # print(f"    Skipping FBX file with unexpected format: {fbx.name}")
            continue
        fbx_gloss = match.group('gloss')
        fbx_number = match.group('number')
        
        if fbx_gloss == gloss and fbx_number == number:
            print(f"    Found matching FBX file: {fbx.name}")
            matching_fbx = fbx
            break  # Stop after finding the first valid match

    if not matching_fbx:
        print("    No matching FBX file found based on gloss and number.")
    return matching_fbx

def update_database(cnx, glos, json_filename, take):
    """
    Updates the 'll_metadata' field in the 'mocap_files' table where 'filename' matches glb_filename.
    """
    try:
        cursor = cnx.cursor()
        # Check if the GLB filename exists in the table
        query = ("SELECT COUNT(*) FROM mocap_files WHERE glos = %s AND take = %s")
        cursor.execute(query, (glos,take))
        result = cursor.fetchone()
        if result[0] > 0:
            # Update ll_metadata
            update_query = ("UPDATE mocap_files SET ll_metadata = %s WHERE glos = %s AND take = %s")
            cursor.execute(update_query, (json_filename, glos, take))
            cnx.commit()
            print(f"    Updated 'll_metadata' for {glos} with {json_filename}.")
        else:
            print(f"    GLB filename {glos} does not exist in the database.")
        
        cursor.close()
    except mysql.connector.Error as err:
        print(f"    Database error: {err}")
        if cursor:
            cursor.close()

def process_json_file(cnx, json_file):
    """
    Processes a single JSON file:
    - Extracts date, gloss, and number from the filename.
    - Filters out files before the cutoff date.
    - Updates the database accordingly.
    """
    print(f"Processing JSON file: {json_file.name}")
    file_date, gloss, number = parse_json_fields(json_file)
    if not gloss or not number:
        return  # Skip if parsing failed or date is before cutoff
    
    print(f"    Extracted Date: {file_date.strftime('%Y-%m-%d')}, Gloss: {gloss}, Number: {number}")

    #extract glos from json filename
    glos = json_file.name.split("_")[1]
    #extract take from json filename
    take = json_file.name.split("_")[3]
    
    # Update the database
    update_database(cnx, glos, json_file.name, take)

# -----------------------------
# Main Execution
# -----------------------------

def main():
    print("Starting Mocap Files Processing Script...")

    # Check if JSON and FBX directories exist
    if not JSON_DIRECTORY.is_dir():
        print(f"Error: JSON directory does not exist: {JSON_DIRECTORY}")
        return
    if not FBX_DIRECTORY.is_dir():
        print(f"Error: FBX directory does not exist: {FBX_DIRECTORY}")
        return

    # Connect to the database
    cnx = connect_to_database(DB_CONFIG)
    if not cnx:
        print("    Exiting due to database connection failure.")
        return

    # Iterate over all JSON files in JSON_DIRECTORY
    json_files = sorted(JSON_DIRECTORY.glob("*.json"))
    if not json_files:
        print(f"No JSON files found in {JSON_DIRECTORY}. Exiting.")
        cnx.close()
        return

    for json_file in json_files:
        process_json_file(cnx, json_file)

    # Close the database connection
    cnx.close()
    print("All processing completed.")

if __name__ == "__main__":
    try:
        main()

        # Send success heartbeat
        monitor.send_heartbeat_with_stats(
            status="success",
            message="LiveLink/Mocap matching completed",
            stats={
                "timestamp": datetime.now().isoformat()
            }
        )

    except Exception as e:
        # Send error heartbeat
        monitor.send_heartbeat_with_stats(
            status="error",
            message=f"LiveLink/Mocap matching failed: {str(e)}",
            stats={"error_type": type(e).__name__}
        )
        raise  # Re-raise to maintain existing error behavior
