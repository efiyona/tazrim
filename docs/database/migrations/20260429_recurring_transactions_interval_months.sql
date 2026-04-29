-- Add interval_months to recurring templates (1=monthly, 2=bimonthly)
ALTER TABLE recurring_transactions
  ADD COLUMN interval_months TINYINT NOT NULL DEFAULT 1
  AFTER day_of_month;

