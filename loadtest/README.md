# Load tests (k6)

Realistic journeys for capacity measurement (S22). **Run against staging only.**

- `PROFILE=smoke` - 20 virtual users for 1 minute (manual CI run).
- `PROFILE=load VUS=2000` - ramp to 2 000 people and hold 20 minutes.
- `PROFILE=peak RPS=1000` - arrival-rate spike (e.g. a job alert).
- `PROFILE=soak VUS=500 DURATION=2h` - memory leaks and slow drift.

Thresholds are the agreed targets: p95 < 300 ms, errors < 0.1%. Demo accounts (PIN 24680) need demo mode on staging.
The full 100 000-person run needs distributed load generators (e.g. k6 cloud or several machines) - see
`docs/capacity.md`.
