# DealershipMGR

**Shop management software for independent auto repair.** Self-hosted, one flat
licence, by [ScriptGain](https://scriptgain.com).

**[Try the live demo →](https://dealership-demo.scriptgain.com)** No signup, click
around a fully loaded shop.

## Who it's for

Independent repair shops, tire shops, quick-lube bays, and fleet workshops. One
bay or twelve. If you are running the shop on a whiteboard, a filing cabinet of
paper ROs, and text messages to customers, this replaces all three.

## What it does

**Take the job in**
Customers request service from your site or you write them up at the counter.
Every request lands in one inbox with the vehicle, the complaint, and the
history attached.

**Quote it and get approval in writing**
Build an estimate, send it, and the customer accepts or declines from a link on
their phone. No "I never approved that" at pickup.

**Do the work**
Repair orders carry parts and labour lines, who is assigned, and what bay it is
in. Schedule jobs against real technician availability so you stop double-booking
the lift.

**Get paid**
Invoice from the completed repair order and take the card yourself. Card
processing runs through **your** Stripe or Authorize.Net account at your rates.
We never touch the money and never take a cut.

**Keep the customer informed**
A customer portal shows their vehicles, open jobs, appointments, invoices, and
past work. Automatic booking confirmations and appointment reminders.

**Run the front of house**
A public site with your service menu, prices, online booking, and a help centre,
themeable without touching code.

## What's built today

The whole request → estimate → repair order → invoice spine is production code,
along with online booking, calendar sync, the customer portal, and payments. On
top of that, the automotive layer is in:

- **Vehicles**: VIN, plate, year/make/model, engine and drivetrain, and an
  odometer recorded with the date it was read. Service history belongs to the
  vehicle, not the customer, so you can see everything done to that truck.
- **Digital vehicle inspections**: your technician records findings with photos
  and a severity, the customer opens a link on their phone with no login, and
  **approves or declines each line individually**. Only approved lines become
  billable work, and pushing them onto the repair order cannot double-bill.
- **Canned jobs**: standard work priced once as book time plus parts, so a brake
  quote is one click. Optional per-job rate override for warranty and fleet work.
- **Service reminders**: due by date or by odometer. The odometer case projects a
  date from how that vehicle is actually driven, so you are not asking customers
  to report their mileage.

## What's coming

- **Parts and suppliers**: catalogue with stock, supplier lookup, cost against
  sell. Canned jobs carry a parts cost today, but no inventory sits behind it.
- **Technician time clock**: clock on and off a repair order

## Why self-hosted

Shopmonkey, Tekmetric, Mitchell 1, and Shop-Ware are all hosted, all priced per
shop or per technician, and all rising. Two things change when you run it
yourself:

1. **The price stops climbing.** One licence, your server, no per-bay meter.
2. **The data is yours.** Ten years of vehicle history is the most valuable asset
   a shop has. It should not live somewhere that can raise your rent or close
   your account.

## Install

Point a fresh Debian or Ubuntu server at your domain and run, as root:

```
DOMAIN=dealership.example.com SSL=1 EMAIL=you@example.com ./deploy/install-master.sh
```

That sets up the web server, database, and certificate. Then open
`https://your.domain/setup` to create the first account and enter your licence
key.

*(A one-line hosted installer is published for the older ScriptGain products and
is not available for DealershipMGR yet.)*

## Where things live

| Surface | Path |
| --- | --- |
| Your public site and booking | `/` |
| Shop panel | `/admin` |
| Customer portal | `/account` |
| First-run setup | `/setup` |

## Running it

Settings are edited in the shop panel, not in files on the server: branding,
prices, tax, payment keys, and email all live in the application.

A handful of maintenance tasks run from the command line:

| Command | What it does |
| --- | --- |
| `php artisan dealership:bootstrap` | Seeds baseline settings and shop defaults. Safe to re-run. |
| `php artisan dealership:license <key>` | Sets or re-checks your licence key. |
| `php artisan dealership:housekeeping` | Prunes stale records. Runs nightly on its own. |
| `php artisan calendar:sync` | Pushes and pulls staff calendar events. |
| `php artisan app:update` | Applies a signed release. |
| `php artisan db-backup:run` | Backs up the database. |
| `php artisan firewall:clear` | Gets you back in if an IP rule locks you out. |

## Requirements

A Linux server with PHP 8.3 and MySQL or MariaDB. A $10/month VPS comfortably
runs a single shop. PHP 8.3 specifically, because the dependency set is pinned to it.

## Licensing

One activation per licence by default, validated against
`https://scriptgain.com/v1`. Buy or manage yours at
[scriptgain.com/products/dealershipmgr](https://scriptgain.com/products/dealershipmgr).
