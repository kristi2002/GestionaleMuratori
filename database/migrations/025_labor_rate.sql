-- Labor cost rate (euros/hour) for turning attendance hours into labor cost.
--   users.hourly_rate         — a worker's pay/charge rate (role='worker')
--   subcontractors.hourly_rate — a subcontractor company's charge rate
-- NULL = no rate set: labor cost is counted as 0 until a rate is configured, so
-- folding labor into the project P&L (FinancialsService) is backward-compatible —
-- existing margins are unchanged until rates exist. A single current rate is kept
-- (no dated history), matching warehouse_items.unit_cost; historical rate changes
-- are not back-dated.
ALTER TABLE users
    ADD COLUMN hourly_rate DECIMAL(10,2) NULL AFTER hire_date;

ALTER TABLE subcontractors
    ADD COLUMN hourly_rate DECIMAL(10,2) NULL AFTER phone;
