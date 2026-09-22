from db_credentials import DB_PASSWORD
import os
import glob
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
    client_id="match-vicon-fbx-csv",
    client_name="Match Vicon FBX/CSV Files",
    description="Matches Vicon FBX and CSV files with database records",
    heartbeat_interval=86400  # Daily at 23:00
)

# MySQL Database Configuration
DB_CONFIG = {
    'user': 'user',
    'password': DB_PASSWORD,
    'host': 'localhost',
    'database': 'admin_gebarenoverleg',
    'raise_on_warnings': True
}

# Directory to search for Vicon files
VICON_DIR = sc_path('media', 'vicon')

def connect_to_db(config):
    try:
        cnx = mysql.connector.connect(**config)
        print("Successfully connected to the database.")
        return cnx
    except mysql.connector.Error as err:
        if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
            print("Error: Invalid credentials")
        elif err.errno == errorcode.ER_BAD_DB_ERROR:
            print("Error: Database does not exist")
        else:
            print(err)
        return None

def fetch_mocap_records(cursor):
    query = "SELECT id, glos, take FROM mocap_files"
    cursor.execute(query)
    return cursor.fetchall()

def find_files(glos, take):
    # Prepare wildcard patterns
    # Example: glos = 'ZONSOPGANG', take = '0'
    fbx_pattern = os.path.join(VICON_DIR, f"{glos}_*_{take}_*_ViconAvatar.fbx")
    csv_pattern = os.path.join(VICON_DIR, f"{glos}_*_{take}_MarkerData_*_ViconAvatar.csv")
    
    print(fbx_pattern)
    print(csv_pattern)

    fbx_files = glob.glob(fbx_pattern)
    csv_files = glob.glob(csv_pattern)

    # Debug: Print found files
    print(f"Searching for glos='{glos}', take='{take}'")
    print(f"FBX Files: {fbx_files}")
    print(f"CSV Files: {csv_files}")

    return fbx_files, csv_files

def update_record(cursor, record_id, fbx_path, csv_path):
    update_query = """
        UPDATE mocap_files
        SET vicon_fbx = %s,
            vicon_csv = %s
        WHERE id = %s
    """
    cursor.execute(update_query, (fbx_path, csv_path, record_id))
    print(f"Updated record ID {record_id} with FBX: {fbx_path}, CSV: {csv_path}")

def main():
    # Connect to the database
    cnx = connect_to_db(DB_CONFIG)
    if not cnx:
        return

    cursor = cnx.cursor(dictionary=True)

    try:
        # Fetch records from mocap_files
        records = fetch_mocap_records(cursor)
        print(f"Fetched {len(records)} records from mocap_files.")

        for record in records:
            record_id = record['id']
            glos = record['glos']
            take = record['take']

            # Find matching files
            fbx_files, csv_files = find_files(glos, take)
        

            if not fbx_files or not csv_files:
                print(f"No matching files found for record ID {record_id} (glos='{glos}', take='{take}').")
                continue  # Skip to the next record

            # Assuming one fbx and one csv per record. Adjust if multiple are expected.
            fbx_path = fbx_files[0] if fbx_files else None
            csv_path = csv_files[0] if csv_files else None

            # Update the record with file paths
            update_record(cursor, record_id, fbx_path, csv_path)

        # Commit all updates
        cnx.commit()
        print("All records have been updated successfully.")

    except mysql.connector.Error as err:
        print(f"Database error: {err}")
        cnx.rollback()
    finally:
        cursor.close()
        cnx.close()
        print("Database connection closed.")

if __name__ == "__main__":
    try:
        main()

        # Send success heartbeat
        monitor.send_heartbeat_with_stats(
            status="success",
            message="Vicon file matching completed",
            stats={
                "timestamp": "success"
            }
        )

    except Exception as e:
        # Send error heartbeat
        monitor.send_heartbeat_with_stats(
            status="error",
            message=f"Vicon file matching failed: {str(e)}",
            stats={"error_type": type(e).__name__}
        )
        raise  # Re-raise to maintain existing error behavior
