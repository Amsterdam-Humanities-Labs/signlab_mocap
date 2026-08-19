#!/usr/bin/env python3
from db_credentials import DB_PASSWORD
import csv
from pathlib import Path
import mysql.connector
from mysql.connector import errorcode
import unicodedata

# Configuration
CSV_FILE = Path("/web/mocap/vocabulary_list_UvA.csv")
DB_CONFIG = {
    'user': 'user',
    'password': DB_PASSWORD,
    'database': 'admin_gebarenoverleg',
    'host': 'signlab-db',
}
TABLE_NAME = 'csl_glosses'

def replace_special_chars(text):
    return unicodedata.normalize('NFKD', text).encode('ascii', 'ignore').decode('ascii')

def connect_to_database(config):
    try:
        cnx = mysql.connector.connect(**config)
        print("Connected to the database.")
        return cnx
    except mysql.connector.Error as err:
        if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
            print("Incorrect username or password.")
        elif err.errno == errorcode.ER_BAD_DB_ERROR:
            print("Database does not exist.")
        else:
            print(err)
        return None

def import_csv_to_db(cnx, csv_file):
    try:
        cursor = cnx.cursor()
        with csv_file.open(newline='', encoding='utf-8') as csvfile:
            reader = csv.DictReader(csvfile)
            for row in reader:
                original_video = row["File Name"].strip()
                glos = replace_special_chars(row["Term"].strip())
                gloss_id = original_video.split('_')[0]
                video = f"{gloss_id}.mp4"
                insert_query = f"INSERT INTO {TABLE_NAME} (video, glos, gloss_id) VALUES (%s, %s, %s)"
                cursor.execute(insert_query, (video, glos, gloss_id))
                print(f"Inserted: {video} -> {glos} with gloss_id {gloss_id}")
        cnx.commit()
        cursor.close()
    except Exception as e:
        print(f"Error importing CSV: {e}")

def main():
    cnx = connect_to_database(DB_CONFIG)
    if not cnx:
        return
    import_csv_to_db(cnx, CSV_FILE)
    cnx.close()
    print("CSV import completed.")

if __name__ == "__main__":
    main()
