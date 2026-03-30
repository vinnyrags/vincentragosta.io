/**
 * SQLite database for pack battles, purchase tracking, and duck races.
 */

const Database = require('better-sqlite3');
const path = require('path');

const db = new Database(path.resolve(__dirname, 'data.db'));

// Enable WAL mode for better concurrent read performance
db.pragma('journal_mode = WAL');

// =========================================================================
// Schema
// =========================================================================

db.exec(`
    CREATE TABLE IF NOT EXISTS purchases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        stripe_session_id TEXT UNIQUE NOT NULL,
        discord_user_id TEXT,
        customer_email TEXT,
        product_name TEXT,
        amount INTEGER,
        created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS purchase_counts (
        discord_user_id TEXT PRIMARY KEY,
        total_purchases INTEGER DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS battles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        channel_message_id TEXT,
        product_slug TEXT NOT NULL,
        product_name TEXT NOT NULL,
        stripe_price_id TEXT,
        max_entries INTEGER DEFAULT 20,
        status TEXT DEFAULT 'open',
        created_at TEXT DEFAULT (datetime('now')),
        closed_at TEXT,
        winner_id TEXT
    );

    CREATE TABLE IF NOT EXISTS battle_entries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        battle_id INTEGER NOT NULL,
        discord_user_id TEXT NOT NULL,
        stripe_session_id TEXT,
        paid INTEGER DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (battle_id) REFERENCES battles(id),
        UNIQUE(battle_id, discord_user_id)
    );

    CREATE TABLE IF NOT EXISTS duck_race_entries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        race_id TEXT NOT NULL,
        discord_user_id TEXT NOT NULL,
        stripe_session_id TEXT,
        created_at TEXT DEFAULT (datetime('now')),
        UNIQUE(race_id, discord_user_id)
    );

    CREATE TABLE IF NOT EXISTS discord_links (
        discord_user_id TEXT PRIMARY KEY,
        customer_email TEXT NOT NULL,
        linked_at TEXT DEFAULT (datetime('now'))
    );
`);

// =========================================================================
// Purchases
// =========================================================================

const stmts = {
    insertPurchase: db.prepare(`
        INSERT OR IGNORE INTO purchases (stripe_session_id, discord_user_id, customer_email, product_name, amount)
        VALUES (?, ?, ?, ?, ?)
    `),

    getPurchaseCount: db.prepare(`
        SELECT total_purchases FROM purchase_counts WHERE discord_user_id = ?
    `),

    incrementPurchaseCount: db.prepare(`
        INSERT INTO purchase_counts (discord_user_id, total_purchases)
        VALUES (?, 1)
        ON CONFLICT(discord_user_id) DO UPDATE SET total_purchases = total_purchases + 1
    `),

    getDiscordIdByEmail: db.prepare(`
        SELECT discord_user_id FROM discord_links WHERE customer_email = ?
    `),

    getEmailByDiscordId: db.prepare(`
        SELECT customer_email FROM discord_links WHERE discord_user_id = ?
    `),

    linkDiscord: db.prepare(`
        INSERT OR REPLACE INTO discord_links (discord_user_id, customer_email) VALUES (?, ?)
    `),
};

// =========================================================================
// Battles
// =========================================================================

const battleStmts = {
    createBattle: db.prepare(`
        INSERT INTO battles (product_slug, product_name, stripe_price_id, max_entries, channel_message_id)
        VALUES (?, ?, ?, ?, ?)
    `),

    getActiveBattle: db.prepare(`
        SELECT * FROM battles WHERE status = 'open' ORDER BY created_at DESC LIMIT 1
    `),

    getBattleById: db.prepare(`
        SELECT * FROM battles WHERE id = ?
    `),

    closeBattle: db.prepare(`
        UPDATE battles SET status = 'closed', closed_at = datetime('now') WHERE id = ?
    `),

    cancelBattle: db.prepare(`
        UPDATE battles SET status = 'cancelled', closed_at = datetime('now') WHERE id = ?
    `),

    setBattleWinner: db.prepare(`
        UPDATE battles SET status = 'complete', winner_id = ? WHERE id = ?
    `),

    setBattleMessage: db.prepare(`
        UPDATE battles SET channel_message_id = ? WHERE id = ?
    `),

    addEntry: db.prepare(`
        INSERT OR IGNORE INTO battle_entries (battle_id, discord_user_id) VALUES (?, ?)
    `),

    confirmPayment: db.prepare(`
        UPDATE battle_entries SET paid = 1, stripe_session_id = ? WHERE battle_id = ? AND discord_user_id = ?
    `),

    getEntries: db.prepare(`
        SELECT * FROM battle_entries WHERE battle_id = ?
    `),

    getPaidEntries: db.prepare(`
        SELECT * FROM battle_entries WHERE battle_id = ? AND paid = 1
    `),

    getEntryCount: db.prepare(`
        SELECT COUNT(*) as count FROM battle_entries WHERE battle_id = ?
    `),

    getPaidEntryCount: db.prepare(`
        SELECT COUNT(*) as count FROM battle_entries WHERE battle_id = ? AND paid = 1
    `),
};

// =========================================================================
// Duck Races
// =========================================================================

const duckStmts = {
    addEntry: db.prepare(`
        INSERT OR IGNORE INTO duck_race_entries (race_id, discord_user_id, stripe_session_id)
        VALUES (?, ?, ?)
    `),

    getEntries: db.prepare(`
        SELECT * FROM duck_race_entries WHERE race_id = ?
    `),
};

module.exports = {
    db,
    purchases: stmts,
    battles: battleStmts,
    ducks: duckStmts,
};
