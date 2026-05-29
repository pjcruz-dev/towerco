-- Allow the app user to provision database-per-tenant (stancl/tenancy).
-- Runs only on first MySQL container init (empty data volume).

GRANT CREATE, DROP, ALTER, REFERENCES, LOCK TABLES, EXECUTE ON *.* TO 'toweros'@'%';
GRANT ALL PRIVILEGES ON `toweros`.* TO 'toweros'@'%';
GRANT ALL PRIVILEGES ON `tenant%`.* TO 'toweros'@'%';
FLUSH PRIVILEGES;
