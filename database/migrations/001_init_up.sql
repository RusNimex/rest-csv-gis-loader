--
-- Компании
--
CREATE TABLE IF NOT EXISTS company (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, 
    name VARCHAR(255) NOT NULL
);

--
-- Доп инфо по компаниям
--
CREATE TABLE IF NOT EXISTS company_contact (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, 
    company_id INT NOT NULL,
    phone VARCHAR(150) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,

    CONSTRAINT FK_company_phone 
    FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
    
    INDEX IX_phone (phone)
);

-- 
-- Регионы 
--
CREATE TABLE IF NOT EXISTS region (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, 
    name VARCHAR(255) NOT NULL
);

-- 
-- Районы 
--
CREATE TABLE IF NOT EXISTS district (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, 
    name VARCHAR(255) NOT NULL
);

--
-- Города 
--
CREATE TABLE IF NOT EXISTS city (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, 
    name VARCHAR(255) NOT NULL
);

-- 
-- Связь регион-район-город 
--
CREATE TABLE IF NOT EXISTS geo (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, 
    region_id INT DEFAULT NULL,
    district_id INT DEFAULT NULL,
    city_id INT DEFAULT NULL,

    CONSTRAINT FK_region 
    FOREIGN KEY (region_id) REFERENCES region(id) ON DELETE SET NULL,

    CONSTRAINT FK_district 
    FOREIGN KEY (district_id) REFERENCES district(id) ON DELETE SET NULL,

    CONSTRAINT FK_city 
    FOREIGN KEY (city_id) REFERENCES city(id) ON DELETE SET NULL
);

-- 
-- Связь компаний и городов, районов, регионов 
--
CREATE TABLE IF NOT EXISTS company_geo (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, 
    geo_id INT NOT NULL,
    company_id INT NOT NULL,

    CONSTRAINT FK_company_geo_company 
    FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,

    CONSTRAINT FK_company_geo_geo 
    FOREIGN KEY (geo_id) REFERENCES geo(id) ON DELETE CASCADE,

    UNIQUE INDEX UIX_company_geo (company_id, geo_id)
);

--
-- Категории компаний
--
CREATE TABLE IF NOT EXISTS category (
    id INT NOT NULL PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

--
-- Подкатегории (не связаны с категориями)
--
CREATE TABLE IF NOT EXISTS subcategory (
    id INT NOT NULL PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

--
-- Связь компаний с категориями
--
CREATE TABLE IF NOT EXISTS company_category (
    company_id INT NOT NULL,
    category_id INT NOT NULL,

    PRIMARY KEY (company_id, category_id),

    CONSTRAINT FK_company_company_category
    FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,

    CONSTRAINT FK_category
    FOREIGN KEY (category_id) REFERENCES category(id) ON DELETE CASCADE
);

--
-- Связь компаний и подкатегорий
--
CREATE TABLE IF NOT EXISTS company_subcategory (
    company_id INT NOT NULL,
    subcategory_id INT NOT NULL,

    PRIMARY KEY (company_id, subcategory_id),

    CONSTRAINT FK_company_company_subcategory
    FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,

    CONSTRAINT FK_subcategory
    FOREIGN KEY (subcategory_id) REFERENCES subcategory(id) ON DELETE CASCADE
);
