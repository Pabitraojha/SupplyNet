if database_not_exists SupplyNet then
    create database SupplyNet;
end if;
use SupplyNet;
if table_exists Users then
    drop table Users;
end if;
if table_exists Products then
    drop table Products;
end if;
if table_exists Orders then
    drop table Orders;
end if;
if table_exists OrderDetails then
    drop table OrderDetails;
end if;
if table_exists Deliveries then
    drop table Deliveries;
end if;
if table_exists CustomersFeedback then
    drop table CustomersFeedback;
end if;
if table_exists routes then
    drop table routes;
end if;


/* 1. create table Users (UserID, UserName, Password, Email, Role (admin, employee, customer, salesman, delivery_person), CreatedAt, UpdatedAt, IsActive, mobile_number, address, profile_picture, markasdeleted) */
CREATE TABLE IF NOT EXISTS Users (
    UserID int primary key auto_increment,
    UserName varchar(255) not null,
    Password varchar(255) not null,
    Email varchar(255) not null unique,
    Role enum('admin', 'employee', 'customer', 'salesman', 'delivery_person') not null,
    CreatedAt timestamp default current_timestamp,
    UpdatedAt timestamp default current_timestamp on update current_timestamp,
    IsActive boolean default true,
    mobile_number varchar(20),
    address text,
    profile_picture varchar(255),
    markasdeleted boolean default false,
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_by int default null
);

/* 2. create table Products (ProductID, ProductName, Description, Price, StockQuantity, markasdeleted) */
CREATE TABLE IF NOT EXISTS Products (
    ProductID int primary key auto_increment,
    ProductName varchar(255) not null,
    Description text,
    Price decimal(10, 2) not null,
    StockQuantity int not null,
    markasdeleted boolean default false
);

/* 3. create table Orders (OrderID, UserID, ProductID, OrderDate, TotalAmount, OrderStatus, ShippingAddress, BillingAddress, DeliveryDate, markasdeleted) */
CREATE TABLE IF NOT EXISTS Orders (
    OrderID int primary key auto_increment,
    UserID int not null,
    ProductID int not null,
    OrderDate timestamp default current_timestamp,
    TotalAmount decimal(10, 2) not null,
    OrderStatus enum('pending', 'processing', 'shipped', 'delivered', 'cancelled') default 'pending',
    ShippingAddress text not null,
    BillingAddress text not null,
    DeliveryDate timestamp null,
    markasdeleted boolean default false
);
/* 4. create table OrderDetails (OrderDetailID, OrderID, ProductID, Quantity, UnitPrice, markasdeleted) */
CREATE TABLE IF NOT EXISTS OrderDetails (
    OrderDetailID int primary key auto_increment,
    OrderID int not null,
    ProductID int not null,
    Quantity int not null,
    UnitPrice decimal(10, 2) not null,
    markasdeleted boolean default false
);
/* 5. create table Deliveries (DeliveryID, OrderID, DeliveryDate, DeliveryStatus, markasdeleted) */
CREATE TABLE IF NOT EXISTS Deliveries (
    DeliveryID int primary key auto_increment,
    OrderID int not null,
    DeliveryDate timestamp null,
    DeliveryStatus enum('pending', 'in_transit', 'delivered') default 'pending',
    markasdeleted boolean default false
);
/* 6. create table CustomersFeedback (FeedbackID, UserID, ProductID, Rating, Comment, FeedbackDate, markasdeleted) */
CREATE TABLE IF NOT EXISTS CustomersFeedback (
    FeedbackID int primary key auto_increment,
    UserID int not null,
    ProductID int not null,
    Rating int not null,
    Comment text,
    FeedbackDate timestamp default current_timestamp,
    markasdeleted boolean default false,
    IsReplied BOOLEAN DEFAULT FALSE,
    AdminReply TEXT NULL
);
/* 7. create table routes (RouteID, StartLocation, EndLocation, Distance, EstimatedTime, markasdeleted) */
CREATE TABLE IF NOT EXISTS routes (
    RouteID int primary key auto_increment,
    StartLocation varchar(255) not null,
    EndLocation varchar(255) not null,
    Distance decimal(10, 2) not null,
    EstimatedTime timestamp null,
    markasdeleted boolean default false
);

CREATE TABLE IF NOT EXISTS Notifications (
    NotificationID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT NULL,
    Role VARCHAR(50) NULL,
    Message TEXT NOT NULL,
    IsRead BOOLEAN DEFAULT FALSE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Users(UserID)
)

/* 8. create table assignRoutes (AssignID, DeliveryID, RouteID, AssignedDate, markasdeleted) */
create table assignRoutes (
    AssignID int primary key auto_increment,
    DeliveryID int not null,
    RouteID int not null,
    AssignedDate timestamp default current_timestamp,
    markasdeleted boolean default false
);

/* create foreign key constraints */
alter table Orders add constraint fk_orders_user_id foreign key (UserID) references Users(UserID);
alter table Orders add constraint fk_orders_product_id foreign key (ProductID) references Products(ProductID);
alter table OrderDetails add constraint fk_order_details_order_id foreign key (OrderID) references Orders(OrderID);
alter table OrderDetails add constraint fk_order_details_product_id foreign key (ProductID) references Products(ProductID);
alter table Deliveries add constraint fk_deliveries_order_id foreign key (OrderID) references Orders(OrderID);
alter table CustomersFeedback add constraint fk_customers_feedback_user_id foreign key (UserID) references Users(UserID);
alter table CustomersFeedback add constraint fk_customers_feedback_product_id foreign key (ProductID) references Products(ProductID);
alter table assignRoutes add constraint fk_assign_routes_delivery_id foreign key (DeliveryID) references Deliveries(DeliveryID);
alter table assignRoutes add constraint fk_assign_routes_route_id foreign key (RouteID) references routes(RouteID);


UPDATE Users SET approval_status = 'approved';

