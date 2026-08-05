# Localization

Section 15 laid the groundwork: the shared chrome, authentication and learner-facing
strings are extracted into `lang/en`, the locale is applied per request, and a switcher
exists but stays hidden until an administrator turns it on.

This is deliberately a **first pass**, not a finished translation layer. What follows is
what exists, and exactly what adding a language involves.

## What is extracted so far

| File | Covers |
|---|---|
| `lang/en/nav.php` | Sidebar, topbar, account menu, notification bell |
| `lang/en/auth.php` | Sign-in, registration, password reset, verification, invitations |
| `lang/en/common.php` | Shared verbs and status words used across many screens |
| `lang/en/learn.php` | The player, progress, assessments, certificates, enrolment |

Not yet extracted: the admin screens (course builder, gradebook, reports, commerce
admin, settings). Those are staff-facing and were left for a later pass on purpose —
translating a 40-field settings form buys far less than translating the pages a learner
actually reads.

## Adding a language

1. **Create the directory.** Copy `lang/en` to `lang/<code>` — for example `lang/yo` for
   Yoruba or `lang/ha` for Hausa — and translate the values. Keep every key; a missing
   key falls back to the key name, which is visible on the page.

2. **Register it.** Add the code to `locales` in `config/settings.php`:

   ```php
   'locales' => [
       'en' => 'English',
       'yo' => 'Yorùbá',
   ],
   ```

   This list is the security boundary as well as the menu: `LocaleController` and
   `SetLocale` both refuse any locale that is not in it, because the value ends up in a
   translation **file path**.

3. **Turn the switcher on.** Settings → General → "Show the language switcher". It stays
   hidden while only one language exists, because a switcher with one option is noise.

4. **Translate the framework's own strings** if you want validation messages localized:
   `php artisan lang:publish` writes `lang/<code>/validation.php` and friends.

## Rules for translators

- **Buttons are verbs.** "Continue learning", not "Continuation". This is the CLAUDE.md
  tone rule and it survives translation — a noun-phrase button reads as a label, not an
  action.
- **The voice is warm, encouraging and academic.** Not corporate, not chummy.
- **Keep the placeholders.** `:count`, `:percent`, `:short` and `:role` are substituted at
  runtime. `learn.attempts_remaining` and `nav.cart_items` use Laravel's pluralization
  syntax (`{0} … |{1} … |[2,*] …`); a language with different plural rules may need a
  different number of branches.
- **Do not translate the three framework keys** at the top of `auth.php` (`failed`,
  `password`, `throttle`) into different key names — Laravel looks them up by those exact
  names.

## How the locale is resolved

Per request, most specific first:

1. the visitor's own choice, held in the session (set by `LocaleController`)
2. the institution default from Settings → General, which
   `SettingsServiceProvider` pushes into `config('app.locale')`
3. whatever `config/app.php` says

`SetLocale` (in the `web` middleware group) applies step 1. Steps 2 and 3 need no code:
they are already `config('app.locale')` by the time the request runs.

## What is NOT localized, and why

- **Rich-text content** — course descriptions, lesson bodies, announcements. These are
  authored per course, not per string; translating them is a content decision (a separate
  course, or a translated lesson), not a `lang/` file.
- **Seeded demo data.** Nigerian names and NIPR programme titles are the real thing.
- **The audit trail.** Entries record what happened at the time, in the language it was
  recorded in. Retranslating history would falsify the record.
- **Dates.** Format is a setting (Settings → General), not a translation. Times are always
  24-hour.
