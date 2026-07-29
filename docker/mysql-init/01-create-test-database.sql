-- The WordPress test suite drops and recreates its tables on every run, so it
-- gets its own database rather than sharing the development site's.
CREATE DATABASE IF NOT EXISTS wordpress_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wordpress'@'%';

FLUSH PRIVILEGES;
