from db_credentials import DB_PASSWORD
import os
import mysql.connector

DB_CONFIG = {
    'user': 'user',
    'password': DB_PASSWORD,
    'database': 'admin_gebarenoverleg',
    'host': 'localhost',
}

def check_mp4_files():
    # Directory to check
    video_directory = "/web/gebarenoverleg_media/llVideos"
    
    # Connect to the database
    connection = mysql.connector.connect(**DB_CONFIG)
    cursor = connection.cursor(dictionary=True)
    
    # Get rows with FBX files
    cursor.execute("SELECT id, filename FROM mocap_files WHERE has_fbx = 1 AND (has_video IS NULL OR has_video = 0)")
    files = cursor.fetchall()
    
    # Initialize counters and a list for missing files
    total_files = len(files)
    existing_files = 0
    missing_files = 0
    missing_file_list = []
    
    # Check each file and update database
    for file in files:
        file_id = file['id']
        fbx_filename = file['filename']
        
        # Convert FBX filename to MP4
        # From: 1-curvedopen_250206_58_GlassesGuyRecord_C_1.fbx
        # To: 20250206_1curvedopen25020657_0.mp4
        
        try:
            # Split the filename by underscores
            parts = fbx_filename.split('_')
            
            # Extract the prefix (name), removing any hyphens
            prefix = parts[0].replace("-", "")  # "1000-B" becomes "1000B"
            
            # For this pattern, we'll search based on just the prefix and vislabLivelink suffix
            # as the dates might not match
            search_pattern = f"{prefix}*_*_vislabLivelink.mp4"
            search_path = os.path.join(video_directory, search_pattern)
            
            # Use glob to find all matching files
            import glob
            matching_files = glob.glob(search_path)
            
            if matching_files:
                # Use the first matching MP4 file
                mp4_filename = os.path.basename(matching_files[0])
                cursor.execute(
                    "UPDATE mocap_files SET has_video = 1, video_filename = %s WHERE id = %s",
                    (mp4_filename, file_id)
                )
                existing_files += 1
            else:
                # MP4 does not exist
                cursor.execute(
                    "UPDATE mocap_files SET has_video = 0 WHERE id = %s",
                    (file_id,)
                )
                missing_files += 1
                missing_file_list.append(f"{fbx_filename} (expected video: {search_pattern})")
        except (IndexError, ValueError):
            # Handle cases where the filename doesn't follow the expected pattern
            cursor.execute(
                "UPDATE mocap_files SET has_video = 0 WHERE id = %s",
                (file_id,)
            )
            missing_files += 1
            missing_file_list.append(f"{fbx_filename} (conversion failed)")
    
    # Commit changes
    connection.commit()
    
    # Print statistics
    print(f"Checked {total_files} files:")
    print(f"  - {existing_files} files found with matching MP4 in {video_directory}")
    print(f"  - {missing_files} files missing MP4")
    
    # Print list of missing files
    if missing_files > 0:
        print("\nFiles missing MP4:")
        for missing_file in missing_file_list:
            print(f"  - {missing_file}")
    
    # Close database connection
    cursor.close()
    connection.close()

if __name__ == "__main__":
    check_mp4_files()
