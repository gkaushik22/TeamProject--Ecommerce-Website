/*
DROP TABLE ADMIN CASCADE CONSTRAINTS;
DROP TABLE TRADER CASCADE CONSTRAINTS;
DROP TABLE CUSTOMER CASCADE CONSTRAINTS;
DROP TABLE USERS CASCADE CONSTRAINTS;
DROP TABLE REPORT CASCADE CONSTRAINTS;
DROP TABLE PRODUCT_REPORT CASCADE CONSTRAINTS;
DROP TABLE PRODUCT CASCADE CONSTRAINTS;
DROP TABLE SHOP CASCADE CONSTRAINTS;
DROP TABLE PRODUCT_CATEGORY CASCADE CONSTRAINTS;
DROP TABLE WISHLIST CASCADE CONSTRAINTS;
DROP TABLE PRODUCT_WISHLIST CASCADE CONSTRAINTS;
DROP TABLE DISCOUNT CASCADE CONSTRAINTS;
DROP TABLE CART CASCADE CONSTRAINTS;
DROP TABLE CART_PRODUCT CASCADE CONSTRAINTS;
DROP TABLE REVIEW CASCADE CONSTRAINTS;
DROP TABLE ORDERR CASCADE CONSTRAINTS;
DROP TABLE ORDER_PRODUCT CASCADE CONSTRAINTS;
DROP TABLE COLLECTION_SLOT CASCADE CONSTRAINTS;
DROP TABLE COUPON CASCADE CONSTRAINTS;
DROP TABLE PAYMENT CASCADE CONSTRAINTS;

-- Create a Database table to represent the "USER"
CREATE TABLE USERS(
    user_id    VARCHAR(10) NOT NULL,
    first_name    VARCHAR(30) NOT NULL,
    last_name    VARCHAR(30) NOT NULL,
    gender    VARCHAR(8) NOT NULL,
    contact    VARCHAR(10) NOT NULL UNIQUE,
    address    VARCHAR(30) NOT NULL,
    usertype    VARCHAR(9) NOT NULL,
    email    VARCHAR(50) NOT NULL UNIQUE,
    password    VARCHAR(4000) NOT NULL,
    CONSTRAINT    pk_USERS PRIMARY KEY (user_id)
);


-- Create a Database table to represent the "ADMIN"
CREATE TABLE ADMIN( 
    user_id    VARCHAR(10) NOT NULL, 
    CONSTRAINT    pk_ADMIN PRIMARY KEY (user_id), 
    FOREIGN KEY(user_id) REFERENCES USERS(user_id)
);

-- Create a Database table to represent the "TRADER"
CREATE TABLE TRADER(
    user_id    VARCHAR(10) NOT NULL,
    license    VARCHAR(20) NOT NULL UNIQUE,
    CONSTRAINT    pk_TRADER PRIMARY KEY (user_id),
    FOREIGN KEY(user_id) REFERENCES USERS(user_id)
);

-- Create a Database table to represent the "CUSTOMER"
CREATE TABLE CUSTOMER(
    user_id    VARCHAR(10) NOT NULL,
    member_since    DATE NOT NULL,
    points_earned    VARCHAR(20),
    CONSTRAINT    pk_CUSTOMER PRIMARY KEY (user_id),
    FOREIGN KEY(user_id) REFERENCES USERS(user_id)
);

-- Create a Database table to represent the "REPORT"
CREATE TABLE REPORT(
    report_id    VARCHAR(8) NOT NULL,
    type    VARCHAR(8) NOT NULL,
    created_on    DATE NOT NULL,
    fk1_user_id    VARCHAR(10) NOT NULL,
    CONSTRAINT    pk_REPORT PRIMARY KEY (report_id),
    CONSTRAINT fk1_REPORT_to_USERS FOREIGN KEY(fk1_user_id) REFERENCES USERS(user_id)
);

-- Create a Database table to represent the "SHOP"
CREATE TABLE SHOP(
    shop_id    VARCHAR(8) NOT NULL,
    name    VARCHAR(20) NOT NULL UNIQUE,
    type    VARCHAR(30) NOT NULL,
    rating    DECIMAL(3,2),
    registered    DATE NOT NULL,
    fk1_user_id    VARCHAR(10) NOT NULL,
    CONSTRAINT    pk_SHOP PRIMARY KEY (shop_id),
    CONSTRAINT fk1_SHOP_to_TRADER FOREIGN KEY(fk1_user_id) REFERENCES TRADER(user_id)
);

-- Create a Database table to represent the "PRODUCT_CATEGORY"
CREATE TABLE PRODUCT_CATEGORY(
    category_id    VARCHAR(8) NOT NULL,
    name    VARCHAR(8) NOT NULL,
    CONSTRAINT    pk_PRODUCT_CATEGORY PRIMARY KEY (category_id)
);

-- Create a Database table to represent the "DISCOUNT"
CREATE TABLE DISCOUNT(
    discount_id    VARCHAR(8) NOT NULL,
    percent    INTEGER NOT NULL,
    started_on    DATE NOT NULL,
    valid_upto    DATE NOT NULL,
    CONSTRAINT    pk_DISCOUNT PRIMARY KEY (discount_id)
);

-- Create a Database table to represent the "PRODUCT"
CREATE TABLE PRODUCT(
    product_id            VARCHAR(8) NOT NULL,
    name                  VARCHAR(20) NOT NULL,
    price                 DECIMAL(10,2) NOT NULL,
    stock                 INTEGER NOT NULL,
    fk1_shop_id           VARCHAR(8) NOT NULL,
    fk2_category_id       VARCHAR(8) NOT NULL,
    fk3_discount_id       VARCHAR(8),
    
    CONSTRAINT pk_PRODUCT PRIMARY KEY (product_id),
    CONSTRAINT fk1_PROD_SHOP FOREIGN KEY (fk1_shop_id) REFERENCES SHOP(shop_id),
    CONSTRAINT fk2_PROD_CAT FOREIGN KEY (fk2_category_id) REFERENCES PRODUCT_CATEGORY(category_id),
    CONSTRAINT fk3_PROD_DISC FOREIGN KEY (fk3_discount_id) REFERENCES DISCOUNT(discount_id)
);


-- Create a Database table to represent the "PRODUCT_REPORT"
CREATE TABLE PRODUCT_REPORT (
    product_id VARCHAR(8) NOT NULL,
    report_id  VARCHAR(8) NOT NULL,
    CONSTRAINT pk_PRODUCT_REPORT PRIMARY KEY (product_id, report_id),
    CONSTRAINT fk_product_id FOREIGN KEY (product_id) REFERENCES PRODUCT(product_id) ON DELETE CASCADE,
    CONSTRAINT fk_report_id FOREIGN KEY (report_id) REFERENCES REPORT(report_id) ON DELETE CASCADE
);


-- Create a Database table to represent the "WISHLIST"
CREATE TABLE WISHLIST(
    wishlist_id    VARCHAR(8) NOT NULL,
    fk1_user_id    VARCHAR(10) NOT NULL,
    CONSTRAINT    pk_WISHLIST PRIMARY KEY (wishlist_id),
    CONSTRAINT fk1_WISHLIST_to_CUSTOMER FOREIGN KEY(fk1_user_id) REFERENCES CUSTOMER(user_id)
);

-- Create a Database table to represent the "PRODUCT_WISHLIST"
CREATE TABLE PRODUCT_WISHLIST (
    wishlist_id VARCHAR(8) NOT NULL,
    product_id  VARCHAR(8) NOT NULL,
    CONSTRAINT pk_PRODUCT_WISHLIST PRIMARY KEY (wishlist_id, product_id),
    CONSTRAINT fk_wishlist_product_wishlist FOREIGN KEY (wishlist_id)
        REFERENCES WISHLIST(wishlist_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_product_product FOREIGN KEY (product_id)
        REFERENCES PRODUCT(product_id)
        ON DELETE CASCADE
);



-- Create a Database table to represent the "CART"
CREATE TABLE CART(
    cart_id    VARCHAR(8) NOT NULL,
    fk1_user_id    VARCHAR(10) NOT NULL,
    CONSTRAINT    pk_CART PRIMARY KEY (cart_id),
    CONSTRAINT fk1_CART_to_CUSTOMER FOREIGN KEY(fk1_user_id) REFERENCES CUSTOMER(user_id)
);

-- Create a Database table to represent the "CART_PRODUCT"
CREATE TABLE CART_PRODUCT (
    cart_id    VARCHAR(8) NOT NULL,
    product_id VARCHAR(8) NOT NULL,
    quantity   INTEGER NOT NULL,
    CONSTRAINT pk_CART_PRODUCT PRIMARY KEY (cart_id, product_id),
    CONSTRAINT fk_cart_product_cart FOREIGN KEY (cart_id)
        REFERENCES CART(cart_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cart_product_product FOREIGN KEY (product_id)
        REFERENCES PRODUCT(product_id)
        ON DELETE CASCADE
);



-- Create a Database table to represent the "COLLECTION_SLOT"
CREATE TABLE COLLECTION_SLOT(
    slot_id    VARCHAR(8) NOT NULL,
    scheduled_day    VARCHAR(20) NOT NULL,
    scheduled_time    VARCHAR(8) NOT NULL,  -- Changed from TIME to VARCHAR because Oracle doesn't have TIME
    scheduled_date    DATE NOT NULL,
    CONSTRAINT    pk_COLLECTION_SLOT PRIMARY KEY (slot_id)
);

-- Create a Database table to represent the "COUPON"
CREATE TABLE COUPON(
    coupon_id    VARCHAR(8) NOT NULL,
    code    VARCHAR(20) NOT NULL UNIQUE,
    discount_id    VARCHAR(8) NOT NULL,
    use_limit    INTEGER,
    expiry_date    DATE,
    CONSTRAINT    pk_COUPON PRIMARY KEY (coupon_id),
    CONSTRAINT fk1_COUPON_to_DISCOUNT FOREIGN KEY(discount_id) REFERENCES DISCOUNT(discount_id)
);

-- Create a Database table to represent the "REVIEW"
CREATE TABLE REVIEW(
    review_id    VARCHAR(8) NOT NULL,
    rating    DECIMAL(3,2) NOT NULL,
    review_text    VARCHAR(4000),
    written_on    DATE NOT NULL,
    fk1_user_id    VARCHAR(10) NOT NULL,
    fk2_product_id    VARCHAR(8) NOT NULL,
    CONSTRAINT    pk_REVIEW PRIMARY KEY (review_id),
    CONSTRAINT fk1_REVIEW_to_CUSTOMER FOREIGN KEY(fk1_user_id) REFERENCES CUSTOMER(user_id),
    CONSTRAINT fk2_REVIEW_to_PRODUCT FOREIGN KEY(fk2_product_id) REFERENCES PRODUCT(product_id)
);

-- Create a Database table to represent the "ORDERR"
CREATE TABLE ORDERR(
    order_id    VARCHAR(8) NOT NULL,
    total_amount    INTEGER NOT NULL,
    status    VARCHAR(30) NOT NULL,
    placed_on    DATE NOT NULL,
    fk1_user_id    VARCHAR(10) NOT NULL,
    fk2_coupon_id    VARCHAR(8),
    fk4_slot_id    VARCHAR(8) NOT NULL,
    CONSTRAINT    pk_ORDERR PRIMARY KEY (order_id),
    CONSTRAINT fk1_ORDERR_to_CUSTOMER FOREIGN KEY(fk1_user_id) REFERENCES CUSTOMER(user_id),
    CONSTRAINT fk2_ORDERR_to_COUPON FOREIGN KEY(fk2_coupon_id) REFERENCES COUPON(coupon_id),
    CONSTRAINT fk4_ORDERR_to_COLLECTION_SLOT FOREIGN KEY(fk4_slot_id) REFERENCES COLLECTION_SLOT(slot_id)
);

-- Create a Database table to represent the "PAYMENT"
CREATE TABLE PAYMENT(
    payment_id    VARCHAR(8) NOT NULL,
    method    VARCHAR(20) NOT NULL,
    payment_status    VARCHAR(30) NOT NULL,
    paid_on    DATE NOT NULL,
    fk1_order_id    VARCHAR(8) NOT NULL,
    CONSTRAINT    pk_PAYMENT PRIMARY KEY (payment_id),
    CONSTRAINT fk1_PAYMENT_to_ORDERR FOREIGN KEY(fk1_order_id) REFERENCES ORDERR(order_id)
);

-- Create a Database table to represent the "ORDER_PRODUCT"
CREATE TABLE ORDER_PRODUCT (
    order_id          VARCHAR(8) NOT NULL,
    product_id        VARCHAR(8) NOT NULL,
    quantity          INTEGER NOT NULL,
    price_at_purchase INTEGER NOT NULL,
    CONSTRAINT pk_ORDER_PRODUCT PRIMARY KEY (order_id, product_id),
    CONSTRAINT fk_order_product_order FOREIGN KEY (order_id)
        REFERENCES ORDERR(order_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_order_product_product FOREIGN KEY (product_id)
        REFERENCES PRODUCT(product_id)
        ON DELETE CASCADE
);


ALTER TABLE ORDERR ADD (
    fk3_payment_id VARCHAR(8),
    CONSTRAINT fk3_ORDERR_to_PAYMENT FOREIGN KEY(fk3_payment_id) REFERENCES PAYMENT(payment_id)
);


ALTER TABLE PRODUCT_CATEGORY ADD image BLOB;
ALTER TABLE PRODUCT ADD image BLOB;
ALTER TABLE COUPON ADD image BLOB;
ALTER TABLE REVIEW ADD image BLOB;
ALTER TABLE ADMIN ADD image BLOB;
ALTER TABLE CUSTOMER ADD image BLOB;
ALTER TABLE TRADER ADD image BLOB;
*/

/*
---Sequence-----
DROP SEQUENCE SEQ_USERS;
DROP SEQUENCE SEQ_REPORT;
DROP SEQUENCE SEQ_SHOP;
DROP SEQUENCE SEQ_PRODUCT_CATEGORY;
DROP SEQUENCE SEQ_PRODUCT;
DROP SEQUENCE SEQ_WISHLIST;
DROP SEQUENCE SEQ_CART;
DROP SEQUENCE SEQ_COLLECTION_SLOT;
DROP SEQUENCE SEQ_COUPON;
DROP SEQUENCE SEQ_REVIEW;
DROP SEQUENCE SEQ_ORDERR;
DROP SEQUENCE SEQ_PAYMENT;
DROP SEQUENCE SEQ_Discount;

-- USERS related
CREATE SEQUENCE SEQ_USERS START WITH 100 INCREMENT BY 1; 

CREATE SEQUENCE SEQ_REPORT START WITH 500 INCREMENT BY 1;
CREATE SEQUENCE SEQ_SHOP START WITH 600 INCREMENT BY 1;
CREATE SEQUENCE SEQ_PRODUCT_CATEGORY START WITH 700 INCREMENT BY 1;
CREATE SEQUENCE SEQ_PRODUCT START WITH 800 INCREMENT BY 1;
CREATE SEQUENCE SEQ_WISHLIST START WITH 900 INCREMENT BY 1;
CREATE SEQUENCE SEQ_CART START WITH 1000 INCREMENT BY 1;
CREATE SEQUENCE SEQ_COLLECTION_SLOT START WITH 1100 INCREMENT BY 1;
CREATE SEQUENCE SEQ_COUPON START WITH 1200 INCREMENT BY 1;
CREATE SEQUENCE SEQ_REVIEW START WITH 1300 INCREMENT BY 1;
CREATE SEQUENCE SEQ_ORDERR START WITH 1400 INCREMENT BY 1;
CREATE SEQUENCE SEQ_PAYMENT START WITH 1500 INCREMENT BY 1;
CREATE SEQUENCE SEQ_Discount START WITH 1600 INCREMENT BY 1;

*/

/*
----Triggers----

DROP TRIGGER trg_users_bi;
DROP TRIGGER trg_report_bi;
DROP TRIGGER trg_shop_bi;
DROP TRIGGER trg_product_category_bi;
DROP TRIGGER trg_product_bi;
DROP TRIGGER trg_wishlist_bi;
DROP TRIGGER trg_cart_bi;
DROP TRIGGER trg_collection_slot_bi;
DROP TRIGGER trg_coupon_bi;
DROP TRIGGER trg_review_bi;
DROP TRIGGER trg_orderr_bi;
DROP TRIGGER trg_payment_bi;
DROP TRIGGER trg_discount_bi;


-- USERS
CREATE OR REPLACE TRIGGER trg_users_bi
BEFORE INSERT ON USERS
FOR EACH ROW
BEGIN
  :NEW.user_id := TO_CHAR(SEQ_USERS.NEXTVAL);
END;
/


-- REPORT
CREATE OR REPLACE TRIGGER trg_report_bi
BEFORE INSERT ON REPORT
FOR EACH ROW
BEGIN
  :NEW.report_id := TO_CHAR(SEQ_REPORT.NEXTVAL);
END;
/

-- SHOP
CREATE OR REPLACE TRIGGER trg_shop_bi
BEFORE INSERT ON SHOP
FOR EACH ROW
BEGIN
  :NEW.shop_id := TO_CHAR(SEQ_SHOP.NEXTVAL);
END;
/

-- PRODUCT_CATEGORY
CREATE OR REPLACE TRIGGER trg_product_category_bi
BEFORE INSERT ON PRODUCT_CATEGORY
FOR EACH ROW
BEGIN
  :NEW.category_id := TO_CHAR(SEQ_PRODUCT_CATEGORY.NEXTVAL);
END;
/

-- PRODUCT
CREATE OR REPLACE TRIGGER trg_product_bi
BEFORE INSERT ON PRODUCT
FOR EACH ROW
BEGIN
  :NEW.product_id := TO_CHAR(SEQ_PRODUCT.NEXTVAL);
END;
/

-- WISHLIST
CREATE OR REPLACE TRIGGER trg_wishlist_bi
BEFORE INSERT ON WISHLIST
FOR EACH ROW
BEGIN
  :NEW.wishlist_id := TO_CHAR(SEQ_WISHLIST.NEXTVAL);
END;
/

-- CART
CREATE OR REPLACE TRIGGER trg_cart_bi
BEFORE INSERT ON CART
FOR EACH ROW
BEGIN
  :NEW.cart_id := TO_CHAR(SEQ_CART.NEXTVAL);
END;
/

-- COLLECTION_SLOT
CREATE OR REPLACE TRIGGER trg_collection_slot_bi
BEFORE INSERT ON COLLECTION_SLOT
FOR EACH ROW
BEGIN
  :NEW.slot_id := TO_CHAR(SEQ_COLLECTION_SLOT.NEXTVAL);
END;
/

-- COUPON
CREATE OR REPLACE TRIGGER trg_coupon_bi
BEFORE INSERT ON COUPON
FOR EACH ROW
BEGIN
  :NEW.coupon_id := TO_CHAR(SEQ_COUPON.NEXTVAL);
END;
/

-- REVIEW
CREATE OR REPLACE TRIGGER trg_review_bi
BEFORE INSERT ON REVIEW
FOR EACH ROW
BEGIN
  :NEW.review_id := TO_CHAR(SEQ_REVIEW.NEXTVAL);
END;
/

-- ORDERR
CREATE OR REPLACE TRIGGER trg_orderr_bi
BEFORE INSERT ON ORDERR
FOR EACH ROW
BEGIN
  :NEW.order_id := TO_CHAR(SEQ_ORDERR.NEXTVAL);
END;
/

-- PAYMENT
CREATE OR REPLACE TRIGGER trg_payment_bi
BEFORE INSERT ON PAYMENT
FOR EACH ROW
BEGIN
  :NEW.payment_id := TO_CHAR(SEQ_PAYMENT.NEXTVAL);
END;
/

-- DISCOUNT
CREATE OR REPLACE TRIGGER trg_discount_bi
BEFORE INSERT ON DISCOUNT
FOR EACH ROW
BEGIN
  :NEW.discount_id := TO_CHAR(SEQ_Discount.NEXTVAL);
END;
/
*/


/*
DROP TRIGGER prevent_multiple_user_roles;
DROP TRIGGER set_member_since;
DROP TRIGGER prevent_negative_stock;
DROP TRIGGER check_valid_rating;
DROP TRIGGER prevent_expired_coupon_insert;
DROP TRIGGER check_cart_stock;
DROP TRIGGER set_price_at_purchase;
DROP TRIGGER check_discount_validity;
DROP TRIGGER reduce_stock_on_order;
DROP TRIGGER validate_order_stock;
DROP TRIGGER add_points_on_review;
DROP TRIGGER enforce_coupon_use_limit;
DROP TRIGGER prevent_duplicate_wishlist;
DROP TRIGGER validate_collection_day;
DROP TRIGGER unique_product_name_per_trader;
DROP TRIGGER prevent_order_in_full_slot;
DROP TRIGGER enforce_24_hour_advance_rule;
DROP TRIGGER limit_products_to_trader;
DROP TRIGGER prevent_duplicate_email;
DROP TRIGGER weekly_report_eligibility;
DROP TRIGGER validate_contact_number;
DROP TRIGGER prevent_order_without_payment;
DROP TRIGGER update_order_and_stock;

CREATE OR REPLACE TRIGGER prevent_multiple_user_roles
BEFORE INSERT ON ADMIN
FOR EACH ROW
DECLARE
    v_exists NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_exists FROM TRADER WHERE user_id = :NEW.user_id;
    IF v_exists > 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'User already exists as a TRADER.');
    END IF;

    SELECT COUNT(*) INTO v_exists FROM CUSTOMER WHERE user_id = :NEW.user_id;
    IF v_exists > 0 THEN
        RAISE_APPLICATION_ERROR(-20002, 'User already exists as a CUSTOMER.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER set_member_since
BEFORE INSERT ON CUSTOMER
FOR EACH ROW
BEGIN
    :NEW.member_since := SYSDATE;
END;
/

CREATE OR REPLACE TRIGGER prevent_negative_stock
BEFORE INSERT OR UPDATE ON PRODUCT
FOR EACH ROW
BEGIN
    IF :NEW.stock < 0 THEN
        RAISE_APPLICATION_ERROR(-20003, 'Stock cannot be negative.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER check_valid_rating
BEFORE INSERT OR UPDATE ON REVIEW
FOR EACH ROW
BEGIN
    IF :NEW.rating < 0 OR :NEW.rating > 5 THEN
        RAISE_APPLICATION_ERROR(-20004, 'Rating must be between 0 and 5.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER prevent_expired_coupon_insert
BEFORE INSERT ON COUPON
FOR EACH ROW
BEGIN
    IF :NEW.expiry_date IS NOT NULL AND :NEW.expiry_date < SYSDATE THEN
        RAISE_APPLICATION_ERROR(-20005, 'Cannot insert expired coupon.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER check_cart_stock
BEFORE INSERT OR UPDATE ON CART_PRODUCT
FOR EACH ROW
DECLARE
    v_stock PRODUCT.stock%TYPE;
BEGIN
    SELECT stock INTO v_stock FROM PRODUCT WHERE product_id = :NEW.product_id;

    IF :NEW.quantity > v_stock THEN
        RAISE_APPLICATION_ERROR(-20006, 'Quantity exceeds available stock.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER set_price_at_purchase
BEFORE INSERT ON ORDER_PRODUCT
FOR EACH ROW
BEGIN
    SELECT price INTO :NEW.price_at_purchase FROM PRODUCT WHERE product_id = :NEW.product_id;
END;
/

CREATE OR REPLACE TRIGGER check_discount_validity
BEFORE INSERT OR UPDATE ON PRODUCT
FOR EACH ROW
DECLARE
    v_valid DATE;
BEGIN
    IF :NEW.fk3_discount_id IS NOT NULL THEN
        SELECT valid_upto INTO v_valid FROM DISCOUNT WHERE discount_id = :NEW.fk3_discount_id;
        IF v_valid < SYSDATE THEN
            RAISE_APPLICATION_ERROR(-20007, 'Assigned discount is expired.');
        END IF;
    END IF;
END;
/

CREATE OR REPLACE TRIGGER reduce_stock_on_order
AFTER INSERT ON ORDER_PRODUCT
FOR EACH ROW
BEGIN
    UPDATE PRODUCT
    SET stock = stock - :NEW.quantity
    WHERE product_id = :NEW.product_id;
END;
/

CREATE OR REPLACE TRIGGER validate_order_stock
BEFORE INSERT ON ORDER_PRODUCT
FOR EACH ROW
DECLARE
    v_stock PRODUCT.stock%TYPE;
BEGIN
    SELECT stock INTO v_stock FROM PRODUCT WHERE product_id = :NEW.product_id;
    IF v_stock < :NEW.quantity THEN
        RAISE_APPLICATION_ERROR(-20008, 'Insufficient stock for product.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER add_points_on_review
AFTER INSERT ON REVIEW
FOR EACH ROW
BEGIN
    UPDATE CUSTOMER
    SET points_earned = NVL(points_earned, 0) + 5
    WHERE user_id = :NEW.fk1_user_id;
END;
/

CREATE OR REPLACE TRIGGER enforce_coupon_use_limit
BEFORE INSERT ON ORDERR
FOR EACH ROW
DECLARE
    v_limit COUPON.use_limit%TYPE;
    v_used  NUMBER;
BEGIN
    IF :NEW.fk2_coupon_id IS NOT NULL THEN
        SELECT use_limit INTO v_limit FROM COUPON WHERE coupon_id = :NEW.fk2_coupon_id;
        SELECT COUNT(*) INTO v_used FROM ORDERR 
        WHERE fk1_user_id = :NEW.fk1_user_id AND fk2_coupon_id = :NEW.fk2_coupon_id;

        IF v_used >= v_limit THEN
            RAISE_APPLICATION_ERROR(-20009, 'Coupon use limit reached.');
        END IF;
    END IF;
END;
/


CREATE OR REPLACE TRIGGER prevent_duplicate_wishlist
BEFORE INSERT ON PRODUCT_WISHLIST
FOR EACH ROW
DECLARE
    v_exists NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_exists FROM PRODUCT_WISHLIST
    WHERE wishlist_id = :NEW.wishlist_id AND product_id = :NEW.product_id;

    IF v_exists > 0 THEN
        RAISE_APPLICATION_ERROR(-20010, 'This product already exists in the wishlist.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER validate_collection_day
BEFORE INSERT OR UPDATE ON COLLECTION_SLOT
FOR EACH ROW
DECLARE
    v_actual_day VARCHAR2(20);
BEGIN
    SELECT TO_CHAR(:NEW.scheduled_date, 'Day') INTO v_actual_day FROM DUAL;
    v_actual_day := TRIM(v_actual_day);

    IF UPPER(v_actual_day) != UPPER(:NEW.scheduled_day) THEN
        RAISE_APPLICATION_ERROR(-20011, 'Scheduled day does not match the actual date.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER unique_product_name_per_trader
BEFORE INSERT ON PRODUCT
FOR EACH ROW
DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM PRODUCT
    WHERE fk1_shop_id = :NEW.fk1_shop_id AND name = :NEW.name;


    IF v_count > 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'Product name must be unique for each trader.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER prevent_order_in_full_slot
BEFORE INSERT ON ORDERR
FOR EACH ROW
DECLARE
    v_items_in_slot NUMBER;
BEGIN
    -- Count total items already booked for this slot
    SELECT NVL(SUM(OP.quantity), 0)
    INTO v_items_in_slot
    FROM ORDERR O
    JOIN ORDER_PRODUCT OP ON O.order_id = OP.order_id
    WHERE O.fk4_slot_id = :NEW.fk4_slot_id
      AND O.status = 'Booked';

    -- Raise error if items in slot >= 20
    IF v_items_in_slot >= 20 THEN
        RAISE_APPLICATION_ERROR(-20003, 'The selected collection slot is full (item limit reached).');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER enforce_24_hour_advance_rule
BEFORE INSERT ON ORDERR
FOR EACH ROW
DECLARE
    v_slot_date COLLECTION_SLOT.scheduled_date%TYPE;
BEGIN
    SELECT scheduled_date INTO v_slot_date
    FROM COLLECTION_SLOT
    WHERE slot_id = :NEW.fk4_slot_id;
    IF v_slot_date - SYSDATE < 1 THEN
        RAISE_APPLICATION_ERROR(-20003, 'Orders must be placed at least 24 hours in advance of the collection slot.');
    END IF;
END;
/ 

CREATE OR REPLACE TRIGGER limit_products_to_trader
BEFORE UPDATE ON PRODUCT
FOR EACH ROW
DECLARE
    v_shop_id SHOP.shop_id%TYPE;
BEGIN
    SELECT shop_id INTO v_shop_id
    FROM SHOP
    WHERE fk1_user_id = :NEW.fk1_shop_id;

    IF v_shop_id != :NEW.fk1_shop_id THEN
        RAISE_APPLICATION_ERROR(-20004, 'Traders can only manage their own products.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER prevent_duplicate_email
BEFORE INSERT ON USERS
FOR EACH ROW
DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM USERS
    WHERE email = :NEW.email;

    IF v_count > 0 THEN
        RAISE_APPLICATION_ERROR(-20005, 'Email address must be unique.');
    END IF;
END;
/


CREATE OR REPLACE TRIGGER weekly_report_eligibility
BEFORE INSERT ON REPORT
FOR EACH ROW
DECLARE
    v_order_status ORDERR.status%TYPE;
BEGIN
    SELECT status INTO v_order_status
    FROM ORDERR
    WHERE order_id = :NEW.fk1_user_id;

    IF v_order_status != 'Delivered' THEN
        RAISE_APPLICATION_ERROR(-20006, 'Reports can only include delivered orders.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER validate_contact_number
BEFORE INSERT OR UPDATE ON USERS
FOR EACH ROW
BEGIN
    IF LENGTH(:NEW.contact) != 10 OR NOT REGEXP_LIKE(:NEW.contact, '^\d{10}$') THEN
        RAISE_APPLICATION_ERROR(-20004, 'Contact number must be exactly 10 numeric digits.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER prevent_order_without_payment
BEFORE INSERT ON ORDERR
FOR EACH ROW
DECLARE
    v_payment_status VARCHAR(30);
BEGIN
    -- Check if payment is paid
    SELECT payment_status
    INTO v_payment_status
    FROM PAYMENT
    WHERE payment_id = :NEW.fk3_payment_id;

    -- If payment is not 'Paid', prevent the order from being placed
    IF v_payment_status != 'Paid' THEN
        RAISE_APPLICATION_ERROR(-20001, 'Order cannot be placed without payment being received.');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER update_order_and_stock
AFTER INSERT OR UPDATE ON PAYMENT
FOR EACH ROW
DECLARE
    v_order_id VARCHAR(8);
BEGIN
    -- Proceed only if payment status is 'Paid'
    IF :NEW.payment_status = 'Paid' THEN
        -- Update the order status to 'Booked'
        UPDATE ORDERR 
        SET status = 'Booked'
        WHERE order_id = :NEW.fk1_order_id;

        -- Reduce stock for each product in the order
        FOR rec IN (SELECT product_id, quantity FROM ORDER_PRODUCT WHERE order_id = :NEW.fk1_order_id) LOOP
            UPDATE PRODUCT
            SET stock = stock - rec.quantity
            WHERE product_id = rec.product_id;
        END LOOP;
    END IF;
END;
/
*/

/*
--Function created for password hashing:
CREATE OR REPLACE FUNCTION hash_password(p_password IN VARCHAR2) 
RETURN VARCHAR2 
IS
    l_salt RAW(16) := UTL_RAW.CAST_TO_RAW(DBMS_RANDOM.STRING('X', 16)); -- Random 16-byte salt
    l_combined RAW(4000);
    l_hash RAW(2000);
BEGIN
    -- Combine the salt and password using UTL_I18N.STRING_TO_RAW
    l_combined := l_salt || UTL_I18N.STRING_TO_RAW(p_password, 'AL32UTF8');

    -- Use UTL_RAW.CAST_TO_RAW as a simpler alternative for hashing (weaker than SHA-256)
    l_hash := UTL_RAW.REVERSE(l_combined); -- Example placeholder for logic (customize as needed)

    -- Return salt and "hash" combined
    RETURN RAWTOHEX(l_salt) || RAWTOHEX(l_hash);
END hash_password;
*/


/*
DROP TRIGGER TRIG_HASH_PASSWORD;
CREATE OR REPLACE TRIGGER TRIG_HASH_PASSWORD
BEFORE INSERT OR UPDATE ON USERS
FOR EACH ROW
BEGIN
:NEW.password := hash_password(:NEW.password);
END;
*/
/*
ALTER TABLE USERS ADD EMAIL_VERIFIED CHAR(1) DEFAULT 'N';
*/

--ALTER TABLE USERS ADD EMAIL_VERIFICATION_TOKEN VARCHAR2(255);

--ALTER TABLE USERS MODIFY (user_id VARCHAR(36));
/*
ALTER TABLE PRODUCT_CATEGORY
MODIFY (name VARCHAR2(20));

ALTER TABLE CUSTOMER DROP COLUMN image;
ALTER TABLE TRADER DROP COLUMN image;
ALTER TABLE ADMIN DROP COLUMN image;

ALTER TABLE SHOP
MODIFY (name VARCHAR2(30));

ALTER TABLE PRODUCT
MODIFY (name VARCHAR2(30));

DROP TRIGGER LIMIT_PRODUCTS_TO_TRADER;

DROP TRIGGER prevent_order_without_payment;

ALTER TABLE COUPON DROP COLUMN image;
ALTER TABLE REVIEW DROP COLUMN image;

DROP TRIGGER weekly_report_eligibility;
*/

/*
CREATE OR REPLACE TRIGGER trg_auto_user_role
AFTER INSERT ON USERS
FOR EACH ROW
BEGIN
    IF UPPER(:NEW.usertype) = 'ADMIN' THEN
        INSERT INTO ADMIN(user_id) VALUES (:NEW.user_id);
    ELSIF UPPER(:NEW.usertype) = 'CUSTOMER' THEN
        INSERT INTO CUSTOMER(user_id, member_since) VALUES (:NEW.user_id, SYSDATE);
    ELSIF UPPER(:NEW.usertype) = 'TRADER' THEN
        INSERT INTO TRADER(user_id, license) VALUES (:NEW.user_id, 'TEMPLICENSE' || :NEW.user_id);
    END IF;
END;
/
*/


--DROP TRIGGER TRIG_HASH_PASSWORD;
--DROP FUNCTION hash_password;

--ALTER TABLE SHOP ADD (approval_status VARCHAR2(20) DEFAULT 'Pending');

--UPDATE SHOP
--SET APPROVAL_STATUS = 'Approved'
--WHERE SHOP_ID = '603' AND FK1_USER_ID = '129';
/*
--PRODUCT ADD---
ALTER TABLE PRODUCT
ADD (
    description VARCHAR2(4000),
    unit VARCHAR2(20),
    image_path VARCHAR2(255),
    is_active NUMBER(1) DEFAULT 1
);
*/
--ALTER TABLE PRODUCT DROP COLUMN IMAGE_PATH;
--ALTER TABLE PRODUCT RENAME COLUMN is_active TO status;
--ALTER TABLE PRODUCT MODIFY status VARCHAR2(20) DEFAULT 'Enable';

-- Add action column
--ALTER TABLE PRODUCT ADD (action VARCHAR2(20));
--ALTER TABLE SHOP ADD ACTION VARCHAR2(20);
--ALTER TABLE TRADER ADD (status VARCHAR2(20), action VARCHAR2(20));
--ALTER TABLE TRADER MODIFY (status VARCHAR2(20) DEFAULT 'Pending');


--INSERT INTO DISCOUNT (discount_id, percent, started_on, valid_upto)
--VALUES ('1600', 10, TO_DATE('2025-05-01', 'YYYY-MM-DD'), TO_DATE('2025-06-01', 'YYYY-MM-DD'));
--INSERT INTO DISCOUNT (discount_id, percent, started_on, valid_upto)
--VALUES ('1601', 20, TO_DATE('2025-05-01', 'YYYY-MM-DD'), TO_DATE('2025-05-10', 'YYYY-MM-DD')); 



--ALTER TABLE COLLECTION_SLOT MODIFY (scheduled_time VARCHAR2(8) NULL);


--INSERT INTO COUPON (code, discount_id, use_limit, expiry_date)
--VALUES ( 'SAVE10MAY25', '1600', 5, TO_DATE('2025-06-01', 'YYYY-MM-DD'));


--ALTER TABLE COLLECTION_SLOT MODIFY (scheduled_time VARCHAR2(11));


--DROP TRIGGER update_order_and_stock;

/*
CREATE OR REPLACE TRIGGER update_order_and_stock
AFTER INSERT OR UPDATE ON PAYMENT
FOR EACH ROW
BEGIN
    IF :NEW.payment_status = 'Paid' THEN
        UPDATE ORDERR 
        SET status = 'Booked'
        WHERE order_id = :NEW.fk1_order_id;
    END IF;
END;
*/
