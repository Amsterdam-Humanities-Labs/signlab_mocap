#!/usr/bin/env python3
"""Old name of convert_livelink_videos.py, kept because pythonCron on production
runs /web/mocap/convert.py by path. Runs the new script."""
import os
import runpy

if __name__ == "__main__":
    runpy.run_path(os.path.join(os.path.dirname(os.path.abspath(__file__)), "convert_livelink_videos.py"),
                   run_name="__main__")
