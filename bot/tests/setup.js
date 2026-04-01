/**
 * Test setup — creates a fresh in-memory SQLite database for each test file.
 */

import Database from 'better-sqlite3';

export function createTestDb() {
    const db = new Database(':memory:');
    db.pragma('journal_mode = WAL');

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
            battle_number INTEGER,
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

        CREATE TABLE IF NOT EXISTS queues (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            status TEXT DEFAULT 'open',
            created_at TEXT DEFAULT (datetime('now')),
            closed_at TEXT,
            duck_race_winner_id TEXT
        );

        CREATE TABLE IF NOT EXISTS queue_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            queue_id INTEGER NOT NULL,
            discord_user_id TEXT,
            customer_email TEXT,
            product_name TEXT,
            quantity INTEGER DEFAULT 1,
            stripe_session_id TEXT,
            created_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (queue_id) REFERENCES queues(id)
        );

        CREATE TABLE IF NOT EXISTS livestream_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            status TEXT DEFAULT 'active',
            created_at TEXT DEFAULT (datetime('now')),
            ended_at TEXT
        );

        CREATE TABLE IF NOT EXISTS livestream_buyers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id INTEGER NOT NULL,
            discord_user_id TEXT,
            customer_email TEXT NOT NULL,
            shipping_paid INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (session_id) REFERENCES livestream_sessions(id),
            UNIQUE(session_id, customer_email)
        );

        CREATE TABLE IF NOT EXISTS discord_links (
            discord_user_id TEXT PRIMARY KEY,
            customer_email TEXT NOT NULL,
            linked_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS duck_race_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            race_id TEXT NOT NULL,
            discord_user_id TEXT NOT NULL,
            stripe_session_id TEXT,
            created_at TEXT DEFAULT (datetime('now')),
            UNIQUE(race_id, discord_user_id)
        );
    `);

    return db;
}

export function buildStmts(db) {
    return {
        purchases: {
            insertPurchase: db.prepare(`INSERT OR IGNORE INTO purchases (stripe_session_id, discord_user_id, customer_email, product_name, amount) VALUES (?, ?, ?, ?, ?)`),
            getPurchaseCount: db.prepare(`SELECT total_purchases FROM purchase_counts WHERE discord_user_id = ?`),
            incrementPurchaseCount: db.prepare(`INSERT INTO purchase_counts (discord_user_id, total_purchases) VALUES (?, 1) ON CONFLICT(discord_user_id) DO UPDATE SET total_purchases = total_purchases + 1`),
            getDiscordIdByEmail: db.prepare(`SELECT discord_user_id FROM discord_links WHERE customer_email = ?`),
            getEmailByDiscordId: db.prepare(`SELECT customer_email FROM discord_links WHERE discord_user_id = ?`),
            linkDiscord: db.prepare(`INSERT OR REPLACE INTO discord_links (discord_user_id, customer_email) VALUES (?, ?)`),
        },
        battles: {
            getNextBattleNumber: db.prepare(`SELECT COALESCE(MAX(battle_number), 0) + 1 as next FROM battles WHERE battle_number IS NOT NULL`),
            createBattle: db.prepare(`INSERT INTO battles (product_slug, product_name, stripe_price_id, max_entries, channel_message_id) VALUES (?, ?, ?, ?, ?)`),
            setBattleNumber: db.prepare(`UPDATE battles SET battle_number = ? WHERE id = ?`),
            getActiveBattle: db.prepare(`SELECT * FROM battles WHERE status = 'open' ORDER BY created_at DESC LIMIT 1`),
            getBattleById: db.prepare(`SELECT * FROM battles WHERE id = ?`),
            closeBattle: db.prepare(`UPDATE battles SET status = 'closed', closed_at = datetime('now') WHERE id = ?`),
            deleteBattle: db.prepare(`DELETE FROM battles WHERE id = ?`),
            cancelBattle: db.prepare(`UPDATE battles SET status = 'cancelled', closed_at = datetime('now') WHERE id = ?`),
            setBattleWinner: db.prepare(`UPDATE battles SET status = 'complete', winner_id = ? WHERE id = ?`),
            setBattleMessage: db.prepare(`UPDATE battles SET channel_message_id = ? WHERE id = ?`),
            addEntry: db.prepare(`INSERT OR IGNORE INTO battle_entries (battle_id, discord_user_id) VALUES (?, ?)`),
            confirmPayment: db.prepare(`UPDATE battle_entries SET paid = 1, stripe_session_id = ? WHERE battle_id = ? AND discord_user_id = ?`),
            getEntries: db.prepare(`SELECT * FROM battle_entries WHERE battle_id = ?`),
            getPaidEntries: db.prepare(`SELECT * FROM battle_entries WHERE battle_id = ? AND paid = 1`),
            getEntryCount: db.prepare(`SELECT COUNT(*) as count FROM battle_entries WHERE battle_id = ?`),
            getPaidEntryCount: db.prepare(`SELECT COUNT(*) as count FROM battle_entries WHERE battle_id = ? AND paid = 1`),
        },
        queues: {
            createQueue: db.prepare(`INSERT INTO queues (status) VALUES ('open')`),
            getActiveQueue: db.prepare(`SELECT * FROM queues WHERE status = 'open' ORDER BY created_at DESC LIMIT 1`),
            getQueueById: db.prepare(`SELECT * FROM queues WHERE id = ?`),
            closeQueue: db.prepare(`UPDATE queues SET status = 'closed', closed_at = datetime('now') WHERE id = ?`),
            setDuckRaceWinner: db.prepare(`UPDATE queues SET status = 'complete', duck_race_winner_id = ? WHERE id = ?`),
            addEntry: db.prepare(`INSERT INTO queue_entries (queue_id, discord_user_id, customer_email, product_name, quantity, stripe_session_id) VALUES (?, ?, ?, ?, ?, ?)`),
            getEntries: db.prepare(`SELECT * FROM queue_entries WHERE queue_id = ? ORDER BY created_at ASC`),
            getUniqueBuyers: db.prepare(`SELECT DISTINCT discord_user_id FROM queue_entries WHERE queue_id = ? AND discord_user_id IS NOT NULL`),
            getEntryCount: db.prepare(`SELECT COUNT(*) as count FROM queue_entries WHERE queue_id = ?`),
            getRecentQueues: db.prepare(`SELECT * FROM queues WHERE status IN ('closed', 'complete') ORDER BY created_at DESC LIMIT ?`),
        },
        livestream: {
            startSession: db.prepare(`INSERT INTO livestream_sessions (status) VALUES ('active')`),
            getActiveSession: db.prepare(`SELECT * FROM livestream_sessions WHERE status = 'active' ORDER BY created_at DESC LIMIT 1`),
            endSession: db.prepare(`UPDATE livestream_sessions SET status = 'ended', ended_at = datetime('now') WHERE id = ?`),
            addBuyer: db.prepare(`INSERT OR IGNORE INTO livestream_buyers (session_id, discord_user_id, customer_email) VALUES (?, ?, ?)`),
            getBuyers: db.prepare(`SELECT * FROM livestream_buyers WHERE session_id = ? AND shipping_paid = 0`),
            markShippingPaid: db.prepare(`UPDATE livestream_buyers SET shipping_paid = 1 WHERE session_id = ? AND customer_email = ?`),
        },
        db,
    };
}
