#!/usr/bin/env python3

import os
import shutil
import subprocess
from pathlib import Path
import sys
sys.path.insert(0, '/home/gomer/pythonCron')
from python_client import ClientMonitor

# Initialize Client Monitor
monitor = ClientMonitor(
    api_url="https://signcollect.nl/client_monitor_api/api.php",
    client_id="convert-livelink-videos",
    client_name="Convert LiveLink Videos",
    description="Converts LiveLink videos from takes directories",
    heartbeat_interval=86400  # Daily at 22:00
)

# -----------------------------
# Configuration
# -----------------------------

# Source directories containing the 'takes' and 'takes2' subdirectories
SOURCE_DIRS = [
        "/web/gebarenoverleg_media/studioFiles/takes",
        "/web/gebarenoverleg_media/studioFiles/takes2",
        "/web/gebarenoverleg_media/studioFiles/takes3",
        "/web/gebarenoverleg_media/studioFiles/takes4",
        "/web/gebarenoverleg_media/studioFiles/takes5",
        "/web/gebarenoverleg_media/studioFiles/takes6",
        "/web/gebarenoverleg_media/studioFiles/takes7",
        "/web/gebarenoverleg_media/studioFiles/takes8",
        "/web/gebarenoverleg_media/studioFiles/takes9",
        "/web/gebarenoverleg_media/studioFiles/takes10",
        "/web/gebarenoverleg_media/studioFiles/takes11",
        "/web/gebarenoverleg_media/studioFiles/takes12",
]

# Destination directories
CSV_DESTINATION = Path("/web/gebarenoverleg_media/llcsv")
VIDEO_DESTINATION = Path("/web/gebarenoverleg_media/llVideos")

# FFmpeg settings for optimal web playback and minimal file size
FFMPEG_VIDEO_CODEC = "libx264"
FFMPEG_AUDIO_CODEC = "aac"
FFMPEG_CRF = "28"                # Constant Rate Factor (lower means better quality and larger size)
FFMPEG_PRESET = "veryslow"       # Encoding speed vs compression (veryslow gives better compression)
FFMPEG_AUDIO_BITRATE = "128k"    # Audio bitrate

# -----------------------------
# Ensure Destination Directories Exist
# -----------------------------
CSV_DESTINATION.mkdir(parents=True, exist_ok=True)
VIDEO_DESTINATION.mkdir(parents=True, exist_ok=True)

# -----------------------------
# Function to Convert Video
# -----------------------------
def convert_video(mov_path: Path, mp4_path: Path):
    """
    Converts a .mov file to .mp4 using ffmpeg with specified settings.
    """
    cmd = [
        "ffmpeg",
        "-i", str(mov_path),
        "-vcodec", FFMPEG_VIDEO_CODEC,
        "-crf", FFMPEG_CRF,
        "-preset", FFMPEG_PRESET,
        "-acodec", FFMPEG_AUDIO_CODEC,
        "-b:a", FFMPEG_AUDIO_BITRATE,
        "-movflags", "+faststart",
        str(mp4_path)
    ]
    try:
        subprocess.run(cmd, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        print(f"    Successfully converted to {mp4_path.name}")
    except subprocess.CalledProcessError:
        print(f"    Error converting {mov_path.name}")
        if mp4_path.exists():
            mp4_path.unlink()  # Remove incomplete file

# -----------------------------
# Function to Process Each Source Directory
# -----------------------------
def process_directory(source_dir: Path):
    """
    Recursively processes the given directory to convert .mov files and copy associated CSV files.
    """
    print(f"Processing source directory: {source_dir}")
    
    mov_files = list(source_dir.rglob("*.mov"))  # Recursive glob
    
    if not mov_files:
        print("    No .mov files found. Skipping processing.")
        return
    
    for mov_file in mov_files:
        base_name = mov_file.stem
        rel_dir = mov_file.parent.relative_to(source_dir) if mov_file.parent != source_dir else Path("")
        print(f"  Found .mov file: {mov_file.name} with base: {base_name} in {rel_dir}")
        
        # Handle associated CSV files matching the base name pattern in the same directory as the mov file
        csv_files = list(mov_file.parent.glob(f"{base_name}*_raw.csv"))
        if csv_files:
            for csv_file in csv_files:
                # Create destination directory with same structure if needed
                csv_dest_dir = CSV_DESTINATION / rel_dir
                csv_dest_dir.mkdir(parents=True, exist_ok=True)
                
                try:
                    shutil.copy(csv_file, csv_dest_dir)
                    print(f"    Copied CSV: {csv_file.name} to {csv_dest_dir}")
                except Exception as e:
                    print(f"    Failed to copy CSV {csv_file.name}: {e}")
        else:
            print(f"    No matching CSV files found for base {base_name}.")
        
        # Define output .mp4 filename and path based on base name
        mp4_filename = f"{base_name}.mp4"
        # Create video destination subdirectory matching source structure
        mp4_dest_dir = VIDEO_DESTINATION
        mp4_dest_dir.mkdir(parents=True, exist_ok=True)
        mp4_output_path = mp4_dest_dir / mp4_filename
        
        # Check if the .mp4 file already exists
        if mp4_output_path.exists():
            print(f"    MP4 file {mp4_filename} already exists. Skipping conversion.")
        else:
            print(f"    Converting {mov_file.name} to {mp4_filename}")
            convert_video(mov_file, mp4_output_path)

# -----------------------------
# Main Execution
# -----------------------------
def main():
    for src_dir in SOURCE_DIRS:
        source_path = Path(src_dir)
        if source_path.is_dir():
            process_directory(source_path)
        else:
            print(f"Source directory does not exist: {src_dir}")
    print("All processing completed.")

if __name__ == "__main__":
    try:
        main()

        # Send success heartbeat
        monitor.send_heartbeat_with_stats(
            status="success",
            message="LiveLink video conversion completed",
            stats={
                "timestamp": "success"
            }
        )

    except Exception as e:
        # Send error heartbeat
        monitor.send_heartbeat_with_stats(
            status="error",
            message=f"LiveLink video conversion failed: {str(e)}",
            stats={"error_type": type(e).__name__}
        )
        raise  # Re-raise to maintain existing error behavior
