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
