-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 05, 2026 at 03:00 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `precious_satin`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `seen` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_order_seen`
--

CREATE TABLE `admin_order_seen` (
  `order_id` int(11) NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_payment_seen`
--

CREATE TABLE `admin_payment_seen` (
  `order_id` int(11) NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1 CHECK (`quantity` > 0),
  `wrapper` varchar(100) DEFAULT NULL,
  `wrapper_price` decimal(10,2) DEFAULT 0.00,
  `ribbon` varchar(100) DEFAULT NULL,
  `ribbon_price` decimal(10,2) DEFAULT 0.00,
  `addons` text DEFAULT NULL,
  `addons_price` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `custom_price` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Readymade'),
(2, 'Satin');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(200) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone`, `message`, `is_read`, `user_id`, `created_at`) VALUES
(1, 'Plinky Quinto', 'quinto.plinkygonzales@gmail.com', '09678772667', 'hi testing', 1, 4, '2026-05-06 10:18:38'),
(2, 'mitch tagalog', 'mitzitagalog@gmail.com', '097862534372', 'hi hehehhe', 1, 5, '2026-05-14 11:49:05'),
(3, 'Plinky Quinto', 'quinto.plinkygonzales@gmail.com', '09923757553', 'hi try', 1, 4, '2026-05-14 23:31:31'),
(4, 'Plinky Quinto', 'quinto.plinkygonzales@gmail.com', '09923757553', 'hi hello', 1, 4, '2026-05-14 23:31:47'),
(5, 'Plinky Quinto', 'quinto.plinkygonzales@gmail.com', '09923757553', 'hi', 1, 4, '2026-05-14 23:39:02'),
(6, 'Plinky Quinto', 'quinto.plinkygonzales@gmail.com', '09923757553', 'sfdasfsdf', 1, 1, '2026-05-22 01:48:19'),
(7, 'Plinky Quinto', 'quinto.plinkygonzales@gmail.com', '09923757553', 'hihADBA', 1, 1, '2026-05-25 00:11:20'),
(8, 'Plinky Quinto', 'quinto.plinkygonzales@gmail.com', '09923757553', 'sjgbdifbgsdfn', 1, 1, '2026-05-25 00:20:24'),
(9, 'James Roger Gadapan', 'jamesroger@gmail.com', '09567283462', 'Hi, I would like to ask if you offer same-day delivery.', 1, 9, '2026-05-26 10:54:37'),
(10, 'James Roger Gadapan', 'jamesroger@gmail.com', '09567283462', 'How long will the delivery take to arrive?', 1, 7, '2026-05-26 10:58:14'),
(11, 'Maria Zyra Faye Macaslang', 'zyrafaye@gmail.com', '09126725376', 'Hi! Do you have available crochet tulips in pink?', 1, 8, '2026-05-26 11:12:22'),
(12, 'Plinky Quinto', 'quinto.plinkygonzales@gmail.com', '09923757553', 'agciADJBN', 1, 1, '2026-05-28 01:35:32'),
(13, 'Plinky Quinto', 'quinto.plinkygonzales@gmail.com', '09923757553', 'hello', 1, 1, '2026-05-28 03:20:58'),
(14, 'juliana sagaral', 'julianasagaral@gmail.com', '09923757553', 'hi i need some fresh rose, do you still have it in stock?', 1, 11, '2026-06-02 07:59:08');

-- --------------------------------------------------------

--
-- Table structure for table `message_replies`
--

CREATE TABLE `message_replies` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `sender` enum('admin','user') NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_replies`
--

INSERT INTO `message_replies` (`id`, `message_id`, `sender`, `body`, `created_at`, `is_read`) VALUES
(1, 2, 'admin', 'hello hehhehe', '2026-05-14 12:26:04', 0),
(2, 4, 'admin', 'yes', '2026-05-14 23:32:49', 0),
(3, 3, 'admin', 'yow', '2026-05-14 23:32:56', 0),
(4, 5, 'admin', 'hello', '2026-05-14 23:39:13', 1),
(5, 5, 'admin', 'hi', '2026-05-14 23:39:42', 1),
(6, 5, 'admin', 'ho', '2026-05-14 23:45:40', 1),
(7, 5, 'user', 'hehe', '2026-05-14 23:50:48', 0),
(8, 5, 'admin', 'hoho', '2026-05-14 23:50:59', 1),
(9, 5, 'admin', 'hello', '2026-05-15 19:43:04', 1),
(10, 5, 'admin', 'bncvx', '2026-05-15 20:00:18', 1),
(11, 8, 'admin', 'dfohgosyhguhgve', '2026-05-25 00:37:53', 1),
(12, 7, 'admin', 'zdhfsdh', '2026-05-25 00:38:23', 1),
(13, 10, 'admin', 'about a week sir', '2026-05-26 11:03:26', 1),
(14, 9, 'admin', 'you can visit our store sir, we cannot entertain a same-day delivery', '2026-05-26 11:03:55', 1),
(15, 11, 'admin', 'yes', '2026-05-26 11:13:25', 0),
(16, 11, 'admin', 'hello', '2026-05-26 11:39:37', 0),
(17, 10, 'admin', 'hi', '2026-05-26 11:40:12', 1),
(18, 10, 'user', 'hello', '2026-05-26 11:40:27', 0),
(19, 10, 'user', '123', '2026-05-26 11:40:36', 0),
(20, 10, 'admin', '123213', '2026-05-26 11:40:50', 1),
(21, 14, 'admin', 'yes!', '2026-06-02 08:06:47', 1),
(22, 14, 'user', 'okay thankyou', '2026-06-02 08:12:48', 0);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_order_no` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `delivery_mode` varchar(50) NOT NULL DEFAULT 'delivery',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `gcash_ref` varchar(30) DEFAULT NULL,
  `gcash_screenshot` varchar(255) DEFAULT NULL,
  `customer_confirmed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `wrapper` varchar(100) DEFAULT NULL,
  `ribbon` varchar(100) DEFAULT NULL,
  `addons` text DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `image` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `stock` int(11) DEFAULT 0 CHECK (`stock` >= 0),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `image`, `category_id`, `stock`, `updated_at`) VALUES
(1, 'Carnation', 'Softly hand-stitched with delicate charm, perfect for lasting beauty.', 300.00, 'crochet/carnation.png', 1, 45, '2026-05-13 18:44:04'),
(2, 'Daisy', 'A cozy, handcrafted bloom that brings a touch of simplicity and joy.', 250.00, 'crochet/daisy.jpg', 1, 45, '2026-05-13 18:44:04');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` between 1 and 5),
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `order_id`, `user_id`, `rating`, `message`, `created_at`) VALUES
(1, 23, 1, 4, 'hi hehehe', '2026-05-16 13:38:39'),
(2, 31, 1, 3, 'fdhrtvew', '2026-05-25 00:36:08'),
(3, 30, 1, 4, 'vrytrhyv', '2026-05-25 00:36:39'),
(4, 35, 7, 5, 'the flowers are so nice!', '2026-05-26 11:04:48'),
(5, 34, 7, 4, 'I like it', '2026-05-26 11:05:04'),
(6, 36, 8, 5, 'Amazing', '2026-05-26 11:14:11'),
(7, 37, 7, 3, 'wow', '2026-05-26 11:22:12'),
(8, 39, 1, 5, 'wow', '2026-05-27 02:00:48'),
(9, 41, 1, 3, 'wow', '2026-05-28 03:26:14'),
(10, 44, 11, 5, 'pretty', '2026-06-02 08:12:19'),
(11, 43, 11, 5, 'cute', '2026-06-02 08:12:32');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `facebook` varchar(100) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `full_name` varchar(150) NOT NULL DEFAULT '',
  `avatar` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `municipality` varchar(120) DEFAULT NULL,
  `gender` enum('Male','Female','Non-binary','Prefer not to say') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `phone`, `address`, `barangay`, `facebook`, `role`, `created_at`, `full_name`, `avatar`, `latitude`, `longitude`, `municipality`, `gender`) VALUES
(1, 'pl_nkyyy', 'plnksgadapan@gmail.com', '$2y$10$8ws7Zctyjt2dSe7pY/rneOBXh7hOaBtq8duZkdAx5RAXBtt27LNZK', '09999999999', 'Purok 1, Bobontugan, Jasaan, Misamis Oriental', 'Bobontugan', 'pl', 'customer', '2026-03-30 14:24:40', 'Plnks Gadapans', 'uploads/avatars/user_1_1779182823.jpg', 8.6536841, 124.7430825, 'Jasaan', 'Female'),
(2, 'admin', 'sereneBlooms@gmail.com', '$2y$10$E2S84hJtK5vjD52poVgyJ.n5n9iVd7xKsXj9Bw7gecxbtoL3VomJ.', NULL, NULL, NULL, NULL, 'admin', '2026-04-09 10:54:27', '', NULL, NULL, NULL, NULL, 'Female'),
(3, 'mariazyrafaye', 'mariazyra@gmail.com', '$2y$10$hKPkWTg32hj6XZ8U7rK.yeO6iXSVDdKjkOOno71uX0nodSKYolmB.', '09727343126', 'Lower Jasaan, Jasaan, Misamis Oriental', 'Lower Jasaan', 'Maria Zyra Faye Macaslang', 'customer', '2026-04-30 09:06:22', 'Maria Zyra Faye', NULL, 8.6493450, 124.7499160, NULL, 'Female'),
(4, 'quinto.plinkygonzales@gmail.com', 'quinto.plinkygonzales@gmail.com', '$2y$10$kQFlxKmyUzeSRnri3eMcf.b/qSoGnXrutk89XjKx.XVx2Xuqyfe02', '09678772667', 'Purok 1, Bobontugan, Jasaan, Misamis Oriental', 'Bobontugan', 'Plinky Gonzales Quinto', 'customer', '2026-05-01 06:57:45', 'Plinky Quinto', 'uploads/avatars/user_10_1777639346.jpeg', 8.6542409, 124.7433507, NULL, 'Female'),
(5, 'mitzitagalog', 'mitzitagalog@gmail.com', '$2y$10$xHtzAu0gSXrzDLaB6QELQu1IM7M3.oERxgvq2q.Nae9vTa1fKLxmW', NULL, 'Poblacion, Lagonglog, Misamis Oriental', 'Poblacion', 'Mitch Tagalog', 'customer', '2026-05-01 13:13:40', 'Minette Mitzi L. Tagalog', 'uploads/avatars/user_11_1777641303.jpg', 8.8061000, 124.7876000, NULL, 'Female'),
(6, 'lucialee', 'lucialeep@gmail.com', '$2y$10$IYv5.yJ7HcaHjKrvDg1Qzui/QKCRy5tWSnNmLZ.biThnY2y5Vi0Uy', '09678772668', 'Camuayan, Balingasag, Misamis Oriental', 'Camuayan', 'Lucia Lee Pacomios', 'customer', '2026-05-19 10:52:21', 'Lucia Lee Pacomios', NULL, 8.7255001, 124.8447597, NULL, 'Female'),
(7, 'jamesgadaps', 'jamesroger@gmail.com', '$2y$10$ioJW28KbfPZyQcP7ZnebQO0SkLJUElU/t63DdzewpXXAA5aTwp42u', '09567283462', 'Coca Cola Plant, Katipunan, Villanueva, Misamis Oriental', 'Katipunan', 'James Roger Gadapan', 'customer', '2026-05-26 10:46:19', 'James Roger Gadapan', 'uploads/avatars/user_9_1779792657.jpg', 8.5786873, 124.7730160, 'Villanueva', 'Male'),
(8, 'zyrafayemacaslang', 'zyrafaye@gmail.com', '$2y$10$bgkB99KODoM2AnQkmlmc4e0VIuwzf9ZrBjHnVXQ9KN4qaM5skyCNq', '09126725376', 'Purok 3, Lower Jasaan, Jasaan, Misamis Oriental', 'Lower Jasaan', 'Maria Zyra Faye Macaslang', 'customer', '2026-05-26 11:10:29', 'Maria Zyra Faye Macaslang', 'uploads/avatars/user_8_1779793906.jpg', 8.6515387, 124.7519499, 'Jasaan', 'Female'),
(9, 'starrubalde', 'starrubalde@gmail.com', '$2y$10$NUNX9UpjVXB.cAx9S/nEZOn0g8aI6uEUBK9FJd0Jd.aWfPtAFv59K', '09823453254', 'Puerto, Puerto, Misamis Oriental', 'Puerto', 'Abrielle Starr Ubalde', 'customer', '2026-05-28 01:38:12', 'Abrielle Starr Ubalde', NULL, 8.4975450, 124.7487883, 'Puerto', 'Female'),
(10, 'fionakate', 'fionakate@gmail.com', '$2y$10$sjkULd2M6qaX2RaLRksu7.knWXyEQ1ojjr.a/xcIiVQld3y0jcbU2', '09234556777', 'Santa Ana, Tagoloan, Misamis Oriental', 'Santa Ana', 'Fiona Kate', 'customer', '2026-05-28 03:31:38', 'Fiona Kate Pederaja', NULL, 8.6468745, 124.7579956, 'Tagoloan', 'Female'),
(11, 'jullianasagaral', 'julianasagaral@gmail.com', '$2y$10$y.e0C0hIGgz1vkRJKxe.R.4fi/arr7mdqxtmYiHjSdsMynBjIGXte', '09527384465', 'Purok 3, Jampason, Jasaan, Misamis Oriental', 'Jampason', 'Julliana Sagaral', 'customer', '2026-06-02 07:56:19', 'Julliana Noelle Sagaral', 'uploads/avatars/user_11_1780387062.jpg', 8.6710044, 124.7395849, 'Jasaan', 'Female');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_order` (`order_id`);

--
-- Indexes for table `admin_order_seen`
--
ALTER TABLE `admin_order_seen`
  ADD PRIMARY KEY (`order_id`);

--
-- Indexes for table `admin_payment_seen`
--
ALTER TABLE `admin_payment_seen`
  ADD PRIMARY KEY (`order_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cm_user_id` (`user_id`);

--
-- Indexes for table `message_replies`
--
ALTER TABLE `message_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mr_message_id` (`message_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_order` (`user_id`,`user_order_no`),
  ADD KEY `idx_orders_user_id` (`user_id`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `idx_orders_created_at` (`created_at`),
  ADD KEY `idx_orders_updated_at` (`updated_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `fk_order_items_product_id` (`product_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review_order_user` (`order_id`,`user_id`),
  ADD KEY `idx_reviews_order_id` (`order_id`),
  ADD KEY `idx_reviews_user_id` (`user_id`),
  ADD KEY `idx_reviews_rating` (`rating`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_username` (`username`),
  ADD UNIQUE KEY `uq_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `message_replies`
--
ALTER TABLE `message_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `message_replies`
--
ALTER TABLE `message_replies`
  ADD CONSTRAINT `fk_mr_message_id` FOREIGN KEY (`message_id`) REFERENCES `contact_messages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
