CREATE TABLE scout_shifts (
  scout_name varchar(100) DEFAULT NULL,
  shifts int DEFAULT NULL
);

CREATE TABLE shift_sales (
  shift_date timestamp NULL DEFAULT NULL,
  shift_time varchar(20) DEFAULT NULL,
  product_name varchar(100) DEFAULT NULL,
  qty_sold int DEFAULT NULL,
  total_sales decimal(10,2) DEFAULT NULL,
  total_donations decimal(10,2) DEFAULT NULL,
  comments varchar(255) DEFAULT NULL
);


CREATE TABLE purchases (
  purchase_date timestamp NULL DEFAULT NULL,
  purchaser_name varchar(100) DEFAULT NULL,
  product_name varchar(100) DEFAULT NULL,
  unit_purchase_price decimal(10,2) DEFAULT NULL,
  unit_sale_price decimal(10,2) DEFAULT NULL,
  qty_purchased int DEFAULT NULL,
  comments varchar(255) DEFAULT NULL
);

CREATE TABLE order_items (
  id int NOT NULL AUTO_INCREMENT,
  order_id int DEFAULT NULL,
  product_name varchar(255) DEFAULT NULL,
  quantity int DEFAULT NULL,
  price_per_item decimal(10,2) DEFAULT NULL,
  subtotal decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY order_id (order_id),
  CONSTRAINT order_items_ibfk_1 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
);

CREATE TABLE orders (
  id int NOT NULL AUTO_INCREMENT,
  customer_name varchar(255) DEFAULT NULL,
  email varchar(255) DEFAULT NULL,
  scout_name varchar(255) DEFAULT NULL,
  payment_mode varchar(50) DEFAULT NULL,
  total_amount decimal(10,2) DEFAULT NULL,
  order_date timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  status varchar(20) DEFAULT 'Pending',
  address varchar(255) DEFAULT NULL,
  comments varchar(255) DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE products (
  id int NOT NULL AUTO_INCREMENT,
  name varchar(255) DEFAULT NULL,
  internal_name varchar(100) DEFAULT NULL,
  price decimal(10,2) DEFAULT NULL,
  image_url varchar(255) DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1
  PRIMARY KEY (id)
);

INSERT INTO products VALUES 
(1,'Meat Sticks (2 pack bundle)', 'Meatsticks', 3.00, 'meatsticks.png', 'blank_image.png', 1),
(2,'Gourmet Chocolate Bar', 'Chocolates', 5.00, 'chocolates.png', 'blank_image.png', 1),
