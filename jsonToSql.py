from db_credentials import DB_PASSWORD
import mysql.connector
import json
import os
import sys


    
def convertToSql():
    
    glosses = {}

    with open("/web/glosses_transformed.json", "r") as file:
        glosses_data = json.load(file)    


    # Function to get glosses from glosses data
    for gloss in glosses_data:
        for gloss_id, gloss_info in gloss.items():
            if gloss_id:
                glosses[gloss_id] = {
                    "gloss_dutch": gloss_info.get("Annotation ID Gloss: Dutch"),
                    "gloss_english": gloss_info.get("Annotation ID Gloss: English"),
                    "senses": gloss_info.get("Senses: Dutch"),
                    "senses_engels": gloss_info.get("Senses: English"),
                    "video": gloss_info.get("Video")
                    }
    
    print(len(glosses))
                    
                

    # Database connection details
    db_config = {
        'host': 'signlab-db',
        'user': 'user',
        'password': DB_PASSWORD,
        'database': 'admin_gebarenoverleg'
    }

    # Connect to the MySQL database
    connection = mysql.connector.connect(**db_config)

    queries = []

    for gloss_id, gloss_info in glosses.items():
            glos = gloss_info.get("gloss_dutch")
            glos_engels = gloss_info.get("gloss_english")
            senses = gloss_info.get("senses")
            senses_engels = gloss_info.get("senses_engels")
            video = gloss_info.get("video")
            #convert senses and senses_engels to string
            try:
                senses = ', '.join(senses)
            except:
                senses = gloss_info.get("senses")
                
            try:
                senses_engels = ', '.join(senses_engels)
            except:
                senses_engels = gloss_info.get("senses_engels")

            query = ("INSERT INTO mocap_data (gloss_id, glos, glos_engels, senses, senses_engels, video) VALUES (%s, %s, %s, %s, %s, %s)", (gloss_id, glos, glos_engels, senses, senses_engels, video))
            queries.append(query)
    print(len(queries))

    cursor = connection.cursor()
    for query in queries:
        cursor.execute(*query)
    connection.commit()

        
#now we want to check fbx file in /web/gebarenoverleg_media/fbx, get the basename and match it with glos in mocap_data. if it exist then we update field take to basename.fbx
def updateTake():
    # Database connection details
    db_config = {
        'host': 'signlab-db',
        'user': 'user',
        'password': DB_PASSWORD,
        'database': 'admin_gebarenoverleg'
    }

    # Connect to the MySQL database
    connection = mysql.connector.connect(**db_config)
    cursor = connection.cursor()
    cursor.execute("SELECT gloss_id, glos FROM mocap_data")
    result = cursor.fetchall()
    for row in result:
        gloss_id = row[0]
        glos = row[1]
        #get basename of fbx file
        fbx_file = "/web/gebarenoverleg_media/fbx/" + glos + ".fbx"
        if os.path.exists(fbx_file):
            cursor.execute("UPDATE mocap_data SET take = %s WHERE gloss_id = %s", (glos + ".fbx", gloss_id))
            connection.commit()
        else:
            print(fbx_file + " does not exist")
            
            

convertToSql()
updateTake()