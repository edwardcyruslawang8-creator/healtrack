# HEALTrack — Local Setup

Getting this project running on a new machine (teammate's laptop, a
reinstall, etc.). Follow these in order.

## 1. Install the base stack

- [XAMPP](https://www.apachefriends.org/) (gives you PHP + Apache)

## 2. Get the code

Clone this repo into your `htdocs` folder, **named exactly `Healtrack`**:

```
C:\xampp\htdocs\Healtrack
```

The folder name has to match — `BASE_URL` in `config/constants.php` is
hardcoded to `/Healtrack`, so the app expects to be reached at
`http://localhost/Healtrack`. If you really want a different folder
name, update that constant to match.

## 3. Install dependencies

From the project root:

```
php composer.phar install
```

This reads `composer.json`/`composer.lock` and rebuilds the `vendor/`
folder with the exact same package versions everyone else is using.
(`composer.phar` is committed in this repo for exactly this reason —
there's no guarantee you have Composer installed globally.)

## 4. Set up the two "soft" secrets

These two files are gitignored (real credentials don't belong in git),
but example templates are committed. Copy each one and drop the
`.example`:

- `config/gemini.example.php` → `config/gemini.php`
- `config/mail.example.php` → `config/mail.php`

You can leave the values inside blank. The app is written to treat an
empty Gemini key / empty mail credentials as "that feature is
unavailable" and skip it gracefully — the chatbot still answers from
its decision tree, it just skips the natural-language phrasing step;
account emails just don't get sent. Only fill in real values if you
have your own Gemini API key / Gmail app password to use.

## 5. Set up the "hard" secret — this is the one that actually gates everything

Unlike the two above, this one isn't optional, and it was never in the
repo in the first place (not even gitignored — it was never placed
inside the project folder at all).

Create this folder:

```
C:\xampp\Healtrack-secure\
```

And place inside it:

- A **Firebase Admin SDK service account** JSON file — referenced by
  `FIREBASE_CREDENTIALS_PATH` in `config/firebase.php`. Without this,
  nothing works: no login, no Firestore reads/writes, nothing degrades
  gracefully here.
- A **Dialogflow service account** JSON file — referenced by
  `DIALOGFLOW_CREDENTIALS_PATH` in `config/dialogflow.php`, needed if
  you'll touch the chatbot's intent-matching step.

Ask whoever manages the project for these two files, sent through a
secure channel (password manager file-sharing, etc.) — never through
GitHub, Slack, or plain email. Ideally, each teammate gets their own
freshly-generated Firebase service account key (Firebase Console →
Project Settings → Service Accounts → Generate new private key)
instead of a shared copy, so access can be revoked per-person later
without rotating everyone's key.

If your filenames differ from what's referenced in `config/firebase.php`
/ `config/dialogflow.php`, update those two constants to match — they're
not secret themselves, just paths.

## 6. Start Apache

Via the XAMPP Control Panel.

## 7. Visit the app

```
http://localhost/Healtrack/
```

---

### Quick troubleshooting

- **Blank page / fatal error mentioning `vendor/autoload.php`** → step 3
  wasn't run yet.
- **Fatal error mentioning `config/gemini.php` or `config/mail.php`** →
  step 4 wasn't done (the file needs to exist, even with blank values).
- **Fatal error mentioning the Firebase/Dialogflow credentials path, or
  nothing loads any real data** → step 5 wasn't done, or the JSON file
  isn't at the exact path `config/firebase.php` / `config/dialogflow.php`
  expect.
