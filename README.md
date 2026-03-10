# AIOps Telemetry Project

This project implements a Laravel-based API emitting ML-ready telemetry (structured logs, correlation IDs, latency) along with a monitoring stack (Prometheus & Grafana) and a Python traffic generator.

## Project Structure
- `app/Http/Controllers/ApiController.php`: Contains all API endpoints including `/api/error`, `/api/slow` (with simulated hard timeouts), `/api/db`, and `/api/validate`.
- `app/Http/Middleware/TelemetryMiddleware.php`: Injects correlation IDs, records request metrics, and outputs structured JSON logs.
- `app/Exceptions/Handler.php`: Categorizes errors into standardized groups (VALIDATION_ERROR, DATABASE_ERROR, SYSTEM_ERROR, TIMEOUT_ERROR).
- `app/Services/MetricsCollector.php`: Gathers Prometheus RED metrics (Counters, Histograms) natively via SQLite atomic upserts.
- `docker-compose.yml` & `prometheus.yml`: Monitoring stack orchestration.
- `traffic_generator.py`: Generates the exact 10-minute traffic pattern needed, outputting `ground_truth.json`.
- `logs.json`: The exported ML-ready dataset extracted from `storage/logs/aiops.log`.
- `report.md`: Engineering Report detailing schema, metrics design, and anomaly orchestration.

## Running Locally
1. Start the monitoring stack and Laravel application:
   ```bash
   docker compose up -d
   ```
2. The exact Grafana dashboard can be provisioned using:
   ```bash
   ./provision_grafana.sh
   ```
3. Run the traffic generator (requires python virtual environment or pip requests):
   ```bash
   python3 traffic_generator.py
   ```
