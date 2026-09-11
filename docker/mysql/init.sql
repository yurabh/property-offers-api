-- Окрема схема під feature-тести, щоб `php artisan test` не чистив dev-дані.
CREATE DATABASE IF NOT EXISTS `property_offers_test`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
