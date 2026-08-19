from db_credentials import DB_PASSWORD
import os
import re
import mysql.connector
import sys

DB_CONFIG = {
    'user': 'user',
    'password': DB_PASSWORD,
    'database': 'admin_gebarenoverleg',
    'host': 'localhost',
}

def check_fbx_files():
    # Directory to check
    fbx_directory = "/web/gebarenoverleg_media/fbx"
    
    # Connect to the database
    connection = mysql.connector.connect(**DB_CONFIG)
    cursor = connection.cursor(dictionary=True)
    
    # First, count and fetch all rows where has_fbx is NULL
    cursor.execute("SELECT COUNT(*) as null_count FROM mocap_files WHERE has_fbx IS NULL")
    null_count_result = cursor.fetchone()
    null_count = null_count_result['null_count']
    
    # Fetch the rows with has_fbx IS NULL for listing
    cursor.execute("SELECT id, filename FROM mocap_files WHERE has_fbx IS NULL")
    null_rows = cursor.fetchall()
    
    # Get filenames from mocap_files table with .glb extension but not .fbx
    cursor.execute("SELECT id, filename FROM mocap_files WHERE has_fbx IS NULL")
    files = cursor.fetchall()
    
    # Initialize counters and a list for missing files
    total_files = len(files)
    existing_files = 0
    missing_files = 0
    found_with_conversion = 0
    missing_file_list = []
    
    # Check each file and update database
    for file in files:
        file_id = file['id']
        filename = file['filename']
        
        # Replace .glb with .fbx in the filename for searching in the fbx directory
        fbx_filename = filename.replace(".glb", ".fbx")
        file_path = os.path.join(fbx_directory, fbx_filename)
        
        if os.path.isfile(file_path):
            # File exists, update database
            cursor.execute(
                "UPDATE mocap_files SET has_fbx = 1, filename = %s WHERE id = %s",
                (fbx_filename, file_id)
            )
            existing_files += 1
        else:
            # Try simplified filename by removing the _GlassesGuyRecord_C_1 suffix
            if "_GlassesGuyRecord_C_1" in filename:
                simplified_filename = filename.replace("_GlassesGuyRecord_C_1.glb", ".fbx")
                simplified_path = os.path.join(fbx_directory, simplified_filename)
                
                if os.path.isfile(simplified_path):
                    # Simplified file exists, update database with has_fbx = 1
                    cursor.execute(
                        "UPDATE mocap_files SET has_fbx = 1, filename = %s WHERE id = %s",
                        (simplified_filename, file_id)
                    )
                    found_with_conversion += 1
                else:
                    # Try generic pattern: remove everything after the third underscore
                    parts = filename.split('_', 3)
                    if len(parts) >= 4:
                        base_name = '_'.join(parts[:3])
                        generic_filename = base_name + ".fbx"
                        generic_path = os.path.join(fbx_directory, generic_filename)
                        
                        if os.path.isfile(generic_path):
                            cursor.execute(
                                "UPDATE mocap_files SET has_fbx = 1, filename = %s WHERE id = %s",
                                (generic_filename, file_id)
                            )
                            found_with_conversion += 1
                        else:
                            missing_files += 1
                            missing_file_list.append(filename)
                    else:
                        missing_files += 1
                        missing_file_list.append(filename)
            else:
                #replace .glb with .fbx in the filename
                filename = filename.replace(".glb", ".fbx")

                generic_filename = filename
                generic_path = os.path.join(fbx_directory, generic_filename)
                # print(generic_path)
                # sys.exit()
                
                if os.path.isfile(generic_path):
                    cursor.execute(
                        "UPDATE mocap_files SET has_fbx = 1, filename = %s WHERE id = %s",
                        (generic_filename, file_id)
                    )
                    found_with_conversion += 1
                else:
                    missing_files += 1
                    missing_file_list.append(filename)
    
    # Commit changes
    connection.commit()
    
    # Print statistics
    print(f"Checked {total_files} files:")
    print(f"  - {existing_files} files found with original filename in {fbx_directory}")
    print(f"  - {found_with_conversion} files found after filename conversion")
    print(f"  - {missing_files} files missing")
    
    # Print list of missing files
    if missing_files > 0:
        print("\nMissing files:")
        for missing_file in missing_file_list:
            print(f"  - {missing_file}")
            
    # Print count and list of rows where has_fbx is NULL
    print(f"\nTotal rows with has_fbx IS NULL: {null_count}")
    if null_count > 0:
        print("\nRows with has_fbx IS NULL:")
        for row in null_rows:
            print(f"  - ID: {row['id']}, Filename: {row['filename']}")
    
    # Close database connection
    cursor.close()
    connection.close()

if __name__ == "__main__":
    check_fbx_files()