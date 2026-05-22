import serial
import oracledb
from datetime import datetime

oracledb.init_oracle_client(lib_dir=r"C:\instantclient_21_17")

# Arduino Serial Port Settings
SERIAL_PORT = 'COM7'  # Update with your actual COM port
BAUD_RATE = 9600

# Oracle DB Connection
DB_USER = "kaushik"
DB_PASS = "kaushik"
DB_CONN = "localhost/XE"   

def get_serial_uid():
    try:
        arduino = serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=10)
        print("Waiting for RFID UID scan...")
        while True:
            line = arduino.readline().decode('utf-8').strip()
            if line.startswith("RFID Tag UID:"):
                uid = line.replace("RFID Tag UID:", "").strip()
                arduino.close()
                return uid
    except serial.SerialException as e:
        print(f"Error reading serial port: {e}")
        return None

def check_uid_exists(cursor, uid):
    # Check by product name or other unique attributes as PRODUCT_UID doesn't exist
    cursor.execute("SELECT COUNT(*) FROM PRODUCT WHERE DESCRIPTION = :1", (uid,))
    result = cursor.fetchone()
    return result[0] > 0

def insert_product(cursor, uid):
    print("Enter product details:")
    product_id = input("Product ID (string of 8 chars, unique): ")  # e.g. 'P1234567'
    name = input("Product Name: ")
    price = float(input("Price: "))
    stock = int(input("Stock Quantity: "))
    fk1_shop_id = input("Shop ID: ")
    fk2_category_id = input("Category ID: ")
    fk3_discount_id = input("Discount ID (optional, press enter to skip): ") or None
    description = input("Description: ")
    unit = input("Unit (e.g., pcs/kg): ")
    status = input("Status (default 'Enable'): ") or "Enable"
    action = input("Action (optional): ") or None

    # --- Image ---
    image_path = input("Image file path (e.g., C:/images/product1.jpg): ")
    with open(image_path, "rb") as img_file:
        image_blob = img_file.read()

    # Save UID in description or another field if needed
    description_with_uid = f"{description} (RFID: {uid})"

    sql = """
        INSERT INTO PRODUCT (
            PRODUCT_ID, NAME, PRICE, STOCK,
            FK1_SHOP_ID, FK2_CATEGORY_ID, FK3_DISCOUNT_ID,
            IMAGE, DESCRIPTION, UNIT, STATUS, ACTION
        ) VALUES (
            :product_id, :name, :price, :stock,
            :fk1_shop_id, :fk2_category_id, :fk3_discount_id,
            :image, :description, :unit, :status, :action
        )
    """
    cursor.execute(sql, {
        'product_id': product_id,
        'name': name,
        'price': price,
        'stock': stock,
        'fk1_shop_id': fk1_shop_id,
        'fk2_category_id': fk2_category_id,
        'fk3_discount_id': fk3_discount_id,
        'image': image_blob,
        'description': description_with_uid,
        'unit': unit,
        'status': status,
        'action': action
    })

def main():
    try:
        conn = oracledb.connect(user=DB_USER, password=DB_PASS, dsn=DB_CONN)
        cursor = conn.cursor()
        print("Connected to Oracle DB successfully.")
    except oracledb.DatabaseError as e:
        print(f"Database connection error: {e}")
        return

    try:
        while True:
            uid = get_serial_uid()
            if not uid:
                print("No UID read. Try again.")
                continue

            print(f"Scanned UID: {uid}")

            if check_uid_exists(cursor, uid):
                print("⚠️  Item already in the database. Please scan a unique item.")
            else:
                print("✅ UID is new. Let's add the product.")
                insert_product(cursor, uid)
                conn.commit()
                print("🎉 Product inserted successfully.")

            cont = input("Scan another? (y/n): ")
            if cont.lower() != 'y':
                break
    except KeyboardInterrupt:
        print("\nProcess interrupted by user.")
    finally:
        cursor.close()
        conn.close()
        print("Connection closed.")

if __name__ == "__main__":
    main()
