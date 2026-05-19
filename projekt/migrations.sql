-- ════════════════════════════════════════════════════
-- MIGRACE — spusť v Admineru (záložka SQL)
-- ════════════════════════════════════════════════════

-- Počítadlo zobrazení + draft status
ALTER TABLE posts
    ADD COLUMN IF NOT EXISTS views       INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS status      VARCHAR(20)  NOT NULL DEFAULT 'published',
    ADD COLUMN IF NOT EXISTS scheduled_at DATETIME    NULL;

-- Záložky
CREATE TABLE IF NOT EXISTS bookmarks (
    user_id    INT      NOT NULL,
    post_id    INT      NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id)  ON DELETE CASCADE
);

-- Tagy
CREATE TABLE IF NOT EXISTS tags (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE
);
CREATE TABLE IF NOT EXISTS post_tags (
    post_id INT NOT NULL,
    tag_id  INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE
);

-- Notifikace
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    type       VARCHAR(50)  NOT NULL,
    message    VARCHAR(500) NOT NULL,
    link       VARCHAR(500) NULL,
    read_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Historie úprav příspěvků
CREATE TABLE IF NOT EXISTS post_revisions (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    post_id   INT          NOT NULL,
    title     VARCHAR(500) NOT NULL,
    content   TEXT         NOT NULL,
    edited_by INT          NOT NULL,
    edited_at DATETIME     NOT NULL,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

-- Newsletter odběratelé
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(255) NOT NULL UNIQUE,
    created_at DATETIME     NOT NULL
);
