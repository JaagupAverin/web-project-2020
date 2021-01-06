CREATE TABLE IF NOT EXISTS restaurant
(
    restaurant_id int PRIMARY KEY AUTO_INCREMENT,
    restaurant_email varchar(50) NOT NULL,
    restaurant_password varchar(256) NOT NULL,
    restaurant_name varchar(100) NOT NULL,
    restaurant_website varchar(500) NOT NULL
);

CREATE TABLE IF NOT EXISTS tag
(
    tag_id int PRIMARY KEY AUTO_INCREMENT,
    tag_name varchar(25) NOT NULL
);

CREATE TABLE IF NOT EXISTS food
(
    food_id int PRIMARY KEY AUTO_INCREMENT,
    food_name varchar(100) NOT NULL,
    food_image varchar(500) NOT NULL,
    food_price double NOT NULL,
    food_sale tinyint DEFAULT 0,
    food_quantity int NOT NULL,
    food_expiration datetime,
    restaurant_id int,
    FOREIGN KEY (restaurant_id) 
        REFERENCES restaurant (restaurant_id) 
);

CREATE TABLE IF NOT EXISTS food_tag
(
	food_id int,
    tag_id int,
    PRIMARY KEY (food_id, tag_id),
    FOREIGN KEY (food_id)
		REFERENCES food (food_id),
	FOREIGN KEY (tag_id)
		REFERENCES tag (tag_id)
);

CREATE TABLE IF NOT EXISTS reservation
(
    reservation_id int PRIMARY KEY AUTO_INCREMENT,
    access_code varchar(5) NOT NULL,
    customer_name varchar(100) NOT NULL,
    customer_contact varchar(16) NOT NULL
);

CREATE TABLE IF NOT EXISTS food_reservation
(
	reservation_id int,
    food_id int,
    food_quantity int NOT NULL,
    food_price double NOT NULL,
    reservation_state tinyint NOT NULL DEFAULT 0,
		-- 0 new
        -- 1 cancelled by customer
        -- 2 seen
        -- 3 cancelled
        -- 4 fulfilled
	FOREIGN KEY (reservation_id)
		REFERENCES reservation (reservation_id),
	FOREIGN KEY (food_id)
		REFERENCES food (food_id),
	PRIMARY KEY (reservation_id, food_id)
);

INSERT INTO tag( tag_name )
    VALUES 
        ( 'No red meat' ),
        ( 'No white meat' ),
        ( 'No fish' ),
        ( 'No gluten' ),
        ( 'No dairy' );
        
INSERT INTO restaurant ( restaurant_email, restaurant_password, restaurant_name, restaurant_website ) 
	VALUES
		("krtoma@ttu.ee","ef797c8118f02dfb649607dd5d3f8c7623048c9c063d532cc95c5ed7a898a64f", "Restaurant 1", "https://google.com"),
		("jaaver@ttu.ee","ef797c8118f02dfb649607dd5d3f8c7623048c9c063d532cc95c5ed7a898a64f", "Restaurant 2", "https://facebook.com");
-- password: "12345678"
INSERT INTO food ( food_name, food_image, food_price, food_sale, food_quantity, food_expiration, restaurant_id )
	VALUES
		( "Potatoes", "potatoes.jpg", 2.99, 25, 15, "2020-05-17 17:00:00", 1 ),
		( "Soup", "soup.jpg", 3.50, 10, 20, "2020-04-17 17:00:00", 1 ),
		( "Hot dog", "hotdog.jpg", 1.5, 10, 12, "2020-04-25 15:00:00", 2 );

INSERT INTO food_tag 
	VALUES
		( 1, 1 ),
		( 1, 2 ),
        ( 1, 3 ),
        ( 1, 4 ),
        ( 1, 5 ),
        ( 2, 1 ),
        ( 2, 3 ),
        ( 2, 5 ),
        ( 3, 5 ),
        ( 3, 3 );

 INSERT INTO reservation (  customer_name, customer_contact, access_code ) 
	VALUES
		 ( "Kristyna", "778003280", "AAAAA"), 
		 ( "Jaagup", "54352698", "BBBBB" ),
		 ( "Elina", "9523332381", "CCCCC"),
		 ( "Kateryna", "773806352", "DDDDD"),
		 ( "Angelica", "3318399094", "EEEEE");
  
 INSERT INTO food_reservation ( reservation_id, food_id, reservation_state, food_quantity, food_price ) 
	VALUES
		 ( 1, 1, 0, 2, 2 ),
		 ( 1, 3, 0, 1, 2 ),
		 ( 2, 2, 1, 4, 2 ),
		 ( 3, 1, 3, 2, 2 ),
		 ( 4, 2, 2, 2, 2 ),
		 ( 5, 1, 4, 2, 2 );