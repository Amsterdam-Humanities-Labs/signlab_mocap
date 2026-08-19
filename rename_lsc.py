import os, re

folder = "/web/gebarenoverleg_media/lsc"
pattern = re.compile(r'^(\d+)_LSC.*\.mp4$', re.IGNORECASE)

for filename in os.listdir(folder):
    match = pattern.match(filename)
    if match:
        new_name = f"{match.group(1)}.mp4"
        os.rename(os.path.join(folder, filename), os.path.join(folder, new_name))
        print(f"Renamed '{filename}' to '{new_name}'")
