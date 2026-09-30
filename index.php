<?php

declare(strict_types=1);

session_start();
date_default_timezone_set('Europe/Paris');

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

const EVENT_DATE = '2026-10-01T07:45:00+02:00';

const INSTAGRAM_GROUP_URL = 'https://ig.me/j/l9grEWjiASJSQW0b';
const SNAPCHAT_GROUP_URL  = 'https://snapchat.com/t/hli5npFI';

/*
|--------------------------------------------------------------------------
| Sécurité HTTP
|--------------------------------------------------------------------------
*/

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

/*
|--------------------------------------------------------------------------
| Base de données
|--------------------------------------------------------------------------
*/

$dataDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'data';

if (!is_dir($dataDirectory)) {
    if (!mkdir($dataDirectory, 0755, true) && !is_dir($dataDirectory)) {
        http_response_code(500);
        exit('Impossible de créer le dossier de données.');
    }
}

$htaccessPath = $dataDirectory . DIRECTORY_SEPARATOR . '.htaccess';

if (!file_exists($htaccessPath)) {
    @file_put_contents(
        $htaccessPath,
        "Require all denied\nDeny from all\n"
    );
}

$databasePath = $dataDirectory . DIRECTORY_SEPARATOR . 'participants.sqlite';

try {
    $pdo = new PDO(
        'sqlite:' . $databasePath,
        null,
        null,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA synchronous = NORMAL;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');

    $pdo->exec(
        '
        CREATE TABLE IF NOT EXISTS participants (
            token_hash TEXT PRIMARY KEY,
            joined_at TEXT NOT NULL
        )
        '
    );
} catch (Throwable) {
    http_response_code(500);
    exit('Impossible d’ouvrir la base de données.');
}

/*
|--------------------------------------------------------------------------
| Cookie participant
|--------------------------------------------------------------------------
*/

const COOKIE_NAME = 'pdr_participant';

$participantToken = $_COOKIE[COOKIE_NAME] ?? '';

if (
    !is_string($participantToken)
    || !preg_match('/^[a-f0-9]{64}$/', $participantToken)
) {
    $participantToken = bin2hex(random_bytes(32));

    setcookie(
        COOKIE_NAME,
        $participantToken,
        [
            'expires' => time() + (86400 * 365),
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

$participantHash = hash('sha256', $participantToken);

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['csrf'];

/*
|--------------------------------------------------------------------------
| Fonctions
|--------------------------------------------------------------------------
*/

function participantCount(PDO $pdo): int
{
    $statement = $pdo->query(
        'SELECT COUNT(*) FROM participants'
    );

    return (int) $statement->fetchColumn();
}

function participantExists(PDO $pdo, string $hash): bool
{
    $statement = $pdo->prepare(
        '
        SELECT 1
        FROM participants
        WHERE token_hash = :token
        LIMIT 1
        '
    );

    $statement->execute([
        ':token' => $hash,
    ]);

    return $statement->fetchColumn() !== false;
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);

    header('Content-Type: application/json; charset=UTF-8');

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| API participation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');

    try {
        $payload = json_decode(
            $raw ?: '{}',
            true,
            32,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException) {
        jsonResponse(
            [
                'success' => false,
                'message' => 'Requête invalide.',
            ],
            400
        );
    }

    $csrf = $payload['csrf'] ?? '';
    $action = $payload['action'] ?? '';

    if (
        !is_string($csrf)
        || !hash_equals($csrfToken, $csrf)
    ) {
        jsonResponse(
            [
                'success' => false,
                'message' => 'Recharge la page puis réessaie.',
            ],
            403
        );
    }

    if ($action !== 'join') {
        jsonResponse(
            [
                'success' => false,
                'message' => 'Action inconnue.',
            ],
            400
        );
    }

    try {
        if (!participantExists($pdo, $participantHash)) {
            $statement = $pdo->prepare(
                '
                INSERT OR IGNORE INTO participants (
                    token_hash,
                    joined_at
                )
                VALUES (
                    :token,
                    :joined
                )
                '
            );

            $statement->execute([
                ':token' => $participantHash,
                ':joined' => date(DATE_ATOM),
            ]);
        }

        jsonResponse([
            'success' => true,
            'count' => participantCount($pdo),
        ]);
    } catch (Throwable) {
        jsonResponse(
            [
                'success' => false,
                'message' => 'Erreur serveur.',
            ],
            500
        );
    }
}

$count = participantCount($pdo);
$alreadyJoined = participantExists($pdo, $participantHash);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#0b0b0d"
    >

    <title>Mobilisation PDR — Pornic</title>

    <link
        rel="stylesheet"
        href="style.css"
    >
</head>

<body>

<div class="background"></div>

<header class="header">

    <a
        href="#top"
        class="brand"
        aria-label="Retour en haut"
    >
        <img
            src="assets/logo.png"
            alt="Logo mobilisation"
            class="brand-logo"
        >
    </a>

    <div class="header-date">
        Pornic · 01/10 · 07:45
    </div>

</header>

<main id="top">

    <section class="hero">

        <div class="hero-label">
            MOBILISATION · JEUDI 1 OCTOBRE
        </div>

        <h1>
            Demain,
            <br>
            <span>07:45.</span>
        </h1>

        <p class="hero-description">
            Rendez-vous devant le Lycée du Pays de Retz à Pornic.
            On se retrouve ensemble dès le matin pour une mobilisation
            pacifique.
        </p>

        <div class="timer">

            <div class="timer-item">
                <strong id="days">00</strong>
                <span>jours</span>
            </div>

            <div class="timer-separator">:</div>

            <div class="timer-item">
                <strong id="hours">00</strong>
                <span>heures</span>
            </div>

            <div class="timer-separator">:</div>

            <div class="timer-item">
                <strong id="minutes">00</strong>
                <span>minutes</span>
            </div>

            <div class="timer-separator">:</div>

            <div class="timer-item">
                <strong id="seconds">00</strong>
                <span>secondes</span>
            </div>

        </div>

        <div class="join-panel">

            <div class="join-count">

                <span
                    class="join-number"
                    id="participantCount"
                >
                    <?= number_format($count, 0, ',', ' ') ?>
                </span>

                <span class="join-text">
                    participant<?= $count !== 1 ? 's' : '' ?>
                    annoncé<?= $count !== 1 ? 's' : '' ?>
                </span>

            </div>

            <button
                type="button"
                class="join-button"
                id="joinButton"
                <?= $alreadyJoined ? 'disabled' : '' ?>
            >
                <span id="joinButtonText">
                    <?= $alreadyJoined
                        ? '✓ Je participe'
                        : 'Je participe' ?>
                </span>
            </button>

        </div>

        <p
            class="join-status"
            id="joinStatus"
        >
            <?= $alreadyJoined
                ? 'Ta participation est déjà comptabilisée.'
                : 'Un clic suffit. Aucune inscription demandée.' ?>
        </p>

    </section>

    <section class="social-section">

        <div class="section-heading">

            <span>RESTER INFORMÉ</span>

            <h2>
                Rejoins les groupes.
            </h2>

            <p>
                Pour suivre les infos et les changements de dernière minute.
            </p>

        </div>

        <div class="social-grid">

            <a
                class="social-card instagram"
                href="<?= htmlspecialchars(INSTAGRAM_GROUP_URL) ?>"
                target="_blank"
                rel="noopener noreferrer"
            >

                <div class="social-icon instagram-icon">

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <rect
                            x="3"
                            y="3"
                            width="18"
                            height="18"
                            rx="5"
                        ></rect>

                        <circle
                            cx="12"
                            cy="12"
                            r="4"
                        ></circle>

                        <circle
                            cx="17.4"
                            cy="6.6"
                            r="1.05"
                            class="fill"
                        ></circle>
                    </svg>

                </div>

                <div class="social-content">

                    <span class="social-platform">
                        Instagram
                    </span>

                    <strong>
                        Rejoindre le groupe
                    </strong>

                </div>

                <span class="social-arrow">
                    ↗
                </span>

            </a>

            <a
                class="social-card snapchat"
                href="<?= htmlspecialchars(SNAPCHAT_GROUP_URL) ?>"
                target="_blank"
                rel="noopener noreferrer"
            >

                <div class="social-icon snapchat-icon">

                    <svg
                        viewBox="0 0 32 32"
                        aria-hidden="true"
                    >
                        <path
                            d="M16 4.25c-4.27 0-7.03 3.14-7.03 7.54 0 1.04.14 2.04.39 2.94-.63.49-1.49.91-2.68 1.17-.85.19-1.25.75-.97 1.35.25.55.95.96 2.15 1.24.78.18 1.28.4 1.63.66.4 2.31 1.76 4.06 4.07 4.8-.16.73-.57 1.53-1.26 2.36 1.63.11 2.88.55 3.8 1.34.52.44 1.19.67 1.9.67s1.38-.23 1.9-.67c.92-.79 2.17-1.23 3.8-1.34-.69-.83-1.1-1.63-1.26-2.36 2.31-.74 3.67-2.49 4.07-4.8.35-.26.85-.48 1.63-.66 1.2-.28 1.9-.69 2.15-1.24.28-.6-.12-1.16-.97-1.35-1.19-.26-2.05-.68-2.68-1.17.25-.9.39-1.9.39-2.94 0-4.4-2.76-7.54-7.03-7.54z"
                        ></path>
                    </svg>

                </div>

                <div class="social-content">

                    <span class="social-platform">
                        Snapchat
                    </span>

                    <strong>
                        Rejoindre le groupe
                    </strong>

                </div>

                <span class="social-arrow">
                    ↗
                </span>

            </a>

        </div>

    </section>

    <section class="info-section">

        <div class="section-heading">

            <span>LE RENDEZ-VOUS</span>

            <h2>
                Ce qu'il faut savoir.
            </h2>

        </div>

        <div class="info-grid">

            <article class="info-card">

                <span class="info-index">
                    01
                </span>

                <h3>
                    Être là avant 07:45
                </h3>

                <p>
                    Arrive quelques minutes avant le début afin que
                    tout le monde puisse se retrouver.
                </p>

            </article>

            <article class="info-card">

                <span class="info-index">
                    02
                </span>

                <h3>
                    Préparer des pancartes
                </h3>

                <p>
                    Une pancarte simple, lisible et avec un message
                    clair suffit largement.
                </p>

            </article>

            <article class="info-card">

                <span class="info-index">
                    03
                </span>

                <h3>
                    Venir équipé
                </h3>

                <p>
                    Prévois du matériel, des pétards, un cadenas de vélo pour verrouiller le portail, un scooter 50cc pour faire du tapage, de la farine et des œufs à jeter sur le lycée. Pas de violence.
                </p>

            </article>

            <article class="info-card">

                <span class="info-index">
                    04
                </span>

                <h3>
                    Rester pacifique
                </h3>

                <p>
                    Pas de violence ni de dégradation.
                    Les sorties de secours et accès d'urgence doivent
                    rester libres.
                </p>

            </article>

        </div>

    </section>

    <section class="location-section">

        <div>

            <span class="location-label">
                RENDEZ-VOUS
            </span>

            <h2>
                Lycée du Pays de Retz
            </h2>

            <p>
                Pornic · Jeudi 1er octobre · 07:45
            </p>

        </div>

        <div class="location-time">
            07:45
        </div>

    </section>

</main>

<footer>

    <div class="footer-line"></div>

    <div class="footer-content">

        <span>
            Mobilisation PDR
        </span>

        <span>
            01.10.2026 · Pornic
        </span>

    </div>

</footer>

<div
    class="toast"
    id="toast"
></div>

<script>
'use strict';

const eventDate = new Date(
    <?= json_encode(
        EVENT_DATE,
        JSON_UNESCAPED_SLASHES
    ) ?>
);

const csrfToken = <?= json_encode(
    $csrfToken,
    JSON_UNESCAPED_SLASHES
) ?>;

const timer = {
    days: document.getElementById('days'),
    hours: document.getElementById('hours'),
    minutes: document.getElementById('minutes'),
    seconds: document.getElementById('seconds')
};

const joinButton =
    document.getElementById('joinButton');

const joinButtonText =
    document.getElementById('joinButtonText');

const joinStatus =
    document.getElementById('joinStatus');

const participantCount =
    document.getElementById('participantCount');

const toast =
    document.getElementById('toast');

let toastTimer = null;

function formatNumber(value) {
    return String(value).padStart(2, '0');
}

function updateTimer() {
    const now = Date.now();

    const difference =
        eventDate.getTime() - now;

    if (difference <= 0) {
        timer.days.textContent = '00';
        timer.hours.textContent = '00';
        timer.minutes.textContent = '00';
        timer.seconds.textContent = '00';

        return;
    }

    const totalSeconds =
        Math.floor(difference / 1000);

    const days =
        Math.floor(totalSeconds / 86400);

    const hours =
        Math.floor(
            (totalSeconds % 86400) / 3600
        );

    const minutes =
        Math.floor(
            (totalSeconds % 3600) / 60
        );

    const seconds =
        totalSeconds % 60;

    timer.days.textContent =
        formatNumber(days);

    timer.hours.textContent =
        formatNumber(hours);

    timer.minutes.textContent =
        formatNumber(minutes);

    timer.seconds.textContent =
        formatNumber(seconds);
}

function showToast(message) {
    toast.textContent = message;

    toast.classList.add('show');

    if (toastTimer !== null) {
        clearTimeout(toastTimer);
    }

    toastTimer = setTimeout(
        () => {
            toast.classList.remove('show');
        },
        2800
    );
}

async function joinMobilisation() {
    if (joinButton.disabled) {
        return;
    }

    joinButton.disabled = true;
    joinButtonText.textContent = 'Ajout...';

    try {
        const response = await fetch(
            window.location.href,
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },

                credentials: 'same-origin',

                body: JSON.stringify({
                    action: 'join',
                    csrf: csrfToken
                })
            }
        );

        const payload =
            await response.json();

        if (
            !response.ok
            || !payload.success
        ) {
            throw new Error(
                payload.message
                || 'Une erreur est survenue.'
            );
        }

        participantCount.textContent =
            new Intl.NumberFormat('fr-FR')
                .format(payload.count);

        joinButtonText.textContent =
            '✓ Je participe';

        joinStatus.textContent =
            'Ta participation est comptabilisée.';

        showToast(
            'Participation ajoutée.'
        );
    } catch (error) {
        joinButton.disabled = false;

        joinButtonText.textContent =
            'Je participe';

        joinStatus.textContent =
            'Impossible de valider pour le moment.';

        showToast(
            error instanceof Error
                ? error.message
                : 'Erreur.'
        );
    }
}

joinButton.addEventListener(
    'click',
    joinMobilisation
);

updateTimer();

setInterval(
    updateTimer,
    1000
);
</script>

</body>
</html>
