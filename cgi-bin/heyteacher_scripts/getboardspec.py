import os
import pymysql
import requests

# ✅ Database credentials
DB_HOST = 'localhost'
DB_USER = 'root'
DB_PASS = 'mysql'
DB_NAME = 'heyteacher_db'

# ✅ Connect to MySQL
conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, db=DB_NAME)
cursor = conn.cursor()

# ✅ Get unique subject + specification link pairs
cursor.execute("""
    SELECT DISTINCT subject, specification_link 
    FROM exam_codes 
    WHERE specification_link IS NOT NULL AND specification_link != ''
""")

rows = cursor.fetchall()

# ✅ Create base directory
base_dir = "subject_specs"
os.makedirs(base_dir, exist_ok=True)

# ✅ Download each PDF
for subject, url in rows:
    try:
        folder_name = subject.replace(" ", "_").replace("/", "-")
        folder_path = os.path.join(base_dir, folder_name)
        os.makedirs(folder_path, exist_ok=True)

        filename = os.path.basename(url.split('?')[0])  # use URL filename
        full_path = os.path.join(folder_path, filename)

        if not os.path.exists(full_path):
            print(f"⬇️ Downloading {subject} spec...")
            response = requests.get(url)
            with open(full_path, "wb") as f:
                f.write(response.content)
            print(f"✅ Saved to {full_path}")
        else:
            print(f"✔️ Already downloaded: {full_path}")

    except Exception as e:
        print(f"❌ Error downloading for {subject}: {e}")

# ✅ Clean up
cursor.close()
conn.close()
