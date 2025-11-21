# Falkenbergs Befolkningsintegration

PHP CLI-applikation som hämtar befolkningsdata från Falkenbergs kommuns API och sparar till Directus CMS med healthcheck-övervakning.

## Funktioner

- Hämtar aktuellt antal folkbokförda invånare från Falkenbergs API
- Sparar data automatiskt till Directus CMS
- Integrerad övervakning via self-hosted healthchecks.io
- Säker hantering av credentials via miljövariabler
- Ingen externa beroenden - använder native PHP cURL

## Installation

### Förutsättningar

- PHP 7.0 eller högre
- PHP cURL-extension aktiverad

### Setup

1. Klona repositoryt:
```bash
git clone https://github.com/Falkenbergs-kommun/integration-fbservice-directus-befolkning.git
cd integration-fbservice-directus-befolkning
```

2. Kopiera och konfigurera miljövariabler:
```bash
cp .env.example .env
```

3. Redigera `.env` och fyll i dina credentials:
```env
FB_DATABASE=Falkenberg
FB_USER=your-username
FB_PASSWORD=your-password
FB_API_URL=https://fb.falkenberg.se/FBservice/Befolkning/folkbokforing/antal

DIRECTUS_URL=https://nav.utvecklingfalkenberg.se/items/befolkning_fb_webb
DIRECTUS_TOKEN=your-directus-bearer-token

HEALTHCHECK_URL=https://healthchecks.utvecklingfalkenberg.se/ping/your-check-uuid
```

## Användning

Kör scriptet manuellt:
```bash
php befolkning.php
```

### Exempel på utdata

```
Healthcheck: Start signal skickad
Hämtar befolkningsdata från Falkenbergs API...
Befolkning i Falkenberg: 47280 personer
Sparar till Directus...
Sparat till Directus!
Directus-svar: {"data":{"id":4,"date_created":"2025-11-21T15:59:35.000Z","befolkning":47280}}
Healthcheck: Success signal skickad
```

## Schemaläggning med Cron

För automatisk körning, lägg till i crontab:

```bash
# Kör varje dag kl 06:00
0 6 * * * cd /path/to/integration-fbservice-directus-befolkning && /usr/bin/php befolkning.php >> /var/log/befolkning-sync.log 2>&1
```

## Healthcheck-övervakning

Applikationen skickar tre typer av signaler till healthchecks.io:

- **Start**: När körningen startar (`/start`)
- **Success**: När allt gått bra
- **Fail**: Vid fel, med felmeddelande i request body (`/fail`)

Detta möjliggör:
- Notifikation om scriptet misslyckas
- Varning om scriptet inte körs inom förväntat tidsintervall
- Loggning av körningshistorik

## Projektstruktur

```
.
├── befolkning.php     # Huvudapplikation
├── .env               # Credentials (ej i git)
├── .env.example       # Mall för miljövariabler
├── .gitignore         # Git-undantag
├── CLAUDE.md          # Dokumentation för Claude Code
└── README.md          # Denna fil
```

## API-dokumentation

### Falkenbergs API
- **Endpoint**: POST `https://fb.falkenberg.se/FBservice/Befolkning/folkbokforing/antal`
- **Query params**: Database, User, Password
- **Response**: JSON med `antalFolkbokforda`

### Directus API
- **Endpoint**: POST till collection `befolkning_fb_webb`
- **Auth**: Bearer token
- **Body**: `{ "befolkning": <number> }`

## Felhantering

Vid fel avslutas scriptet med exit code 1 och skickar:
- Felmeddelande till healthchecks.io
- Detaljerad felbeskrivning till stdout

## Säkerhet

- Credentials lagras i `.env` som är exkluderad från git
- Bearer token för Directus-autentisering
- Inga lösenord i kod eller loggar
