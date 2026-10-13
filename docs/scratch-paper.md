**Scheduler — single daily EOD pipeline**
One orchestrator command market:pull-eod dispatched by Laravel scheduler, run on AWS as an EventBridge → ECS task (or cron on EC2) calling schedule:run.

- Timing (PHT): first pass ~18:30 — after BAP (~4pm), PDS BVAL (5pm publish), PSE close (3pm + 15-min delay), and PSA releases. Retry passes at 20:00 and 21:30 for any source that failed or returned stale data.
- International sources (EIA Brent, gold, wheat via vendor): their "latest daily" is the prior US close — pulled in the same run, dated timezone-aware.
- Business-day awareness: a market_holidays table; weekends/holidays expect no new local data, so no false "stale" alerts.
- Morning digest ~7:00 AM: "Today's rate sheet is ready" or "Missing: LME, BVAL — last good value shown."
