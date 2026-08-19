#!/usr/bin/env python3

import os
import requests
import sys
from pathlib import Path

# Configuration
FBX_DIRECTORY = Path("/web/gebarenoverleg_media/vicon")
OUTPUT_DIRECTORY = Path("/web/gebarenoverleg_media/vicon")  # Change as needed
UPLOAD_URL = "https://leffe.science.uva.nl:8043/fbx2glb/upload"

# Create the output directory if it doesn't exist
OUTPUT_DIRECTORY.mkdir(parents=True, exist_ok=True)

def convert_fbx_to_glb(fbx_path, output_dir):
    """
    Uploads an FBX file to the server and saves the returned GLB file.
    
    Args:
        fbx_path (Path): Path to the FBX file.
        output_dir (Path): Directory to save the GLB file.
    """
    basename = fbx_path.stem
    glb_filename = basename + ".glb"
    glb_path = output_dir / glb_filename

    print(f"Processing '{fbx_path.name}'...")

    try:
        with fbx_path.open('rb') as fbx_file:
            files = {'file': (fbx_path.name, fbx_file, 'application/octet-stream')}
            response = requests.post(UPLOAD_URL, files=files, timeout=60, verify=False)

        response.raise_for_status()  # Raise an error for bad status codes

        # Assuming the server returns the GLB binary directly
        with glb_path.open('wb') as glb_file:
            glb_file.write(response.content)

        print(f"Successfully saved GLB as '{glb_filename}'.")

    except requests.exceptions.RequestException as e:
        print(f"Error uploading '{fbx_path.name}': {e}")
    except Exception as e:
        print(f"Unexpected error processing '{fbx_path.name}': {e}")

def main():
    if not FBX_DIRECTORY.exists() or not FBX_DIRECTORY.is_dir():
        print(f"FBX directory '{FBX_DIRECTORY}' does not exist or is not a directory.")
        sys.exit(1)

    fbx_files = list(FBX_DIRECTORY.glob("*.fbx"))

    if not fbx_files:
        print(f"No FBX files found in '{FBX_DIRECTORY}'.")
        sys.exit(0)

    print(f"Found {len(fbx_files)} FBX file(s) in '{FBX_DIRECTORY}'.\n")

    for fbx_file in fbx_files:
        convert_fbx_to_glb(fbx_file, OUTPUT_DIRECTORY)

    print("\nAll files processed.")

if __name__ == "__main__":
    main()
