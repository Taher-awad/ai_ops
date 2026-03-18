# AIOps Telemetry & Monitoring Engineering Report

## 1. Log Schema Design

The logs are designed with a strict, stable JSON schema using Monolog's `JsonFormatter` to ensure they are machine-readable and easily digestible by log aggregators or anomaly detection models.

**Fields and Rationale:**
- `request_id`: Traces a single request lifecycle for correlation. Missing IDs are auto-generated.
- `client_ip`: Captures origin network. Useful for identifying DDoS patterns or geographical anomalies.
- `user_agent`: Differentiates manual versus automated usage.
- `query`: Preserves query parameters, isolating payload-based edge cases.
- `payload_size_bytes` & `response_size_bytes`: Measures incoming/outgoing data volumes, helping identify data exfiltration or massive payload attacks.
- `route_name`: Categorizes load effectively across endpoints without risking high cardinality.
- `severity`: Explicit `info` or `error` grouping for fast triage.
- `build_version`: Correlates errors to specific deployments.
- `host`: Identifies exact pod/VM handling the traffic.
- `latency_ms` & `status_code`: Essential Golden Signals for identifying service degradation.
- `error_category`: Normalizes exceptions (VALIDATION_ERROR, DATABASE_ERROR, SYSTEM_ERROR, TIMEOUT_ERROR) directly in the application layer.

## 2. Metrics Design

Prometheus metrics following the RED (Rate, Errors, Duration) framework were implemented natively.

- `http_requests_total`: A monotonically increasing counter partitioned by `method`, `path`, and `status`. Used to calculate Request Rate (RPS).
- `http_errors_total`: Similar counter, but partitioned by `error_category` instead of HTTP status. This decouples error analysis from strict HTTP status codes (e.g., a 200 OK could still signify a TIMEOUT_ERROR).
- `http_request_duration_seconds_bucket`: A cumulative histogram capturing request durations.
  - **Buckets (`0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0`)**: These boundaries efficiently capture the majority of healthy traffic (0-250ms), highlight moderate degradation (0.5s - 2.5s), and accurately pinpoint our orchestrated simulated soft timeouts (5-7 seconds) and hard crashes (>10s).

Label explosion is avoided by **excluding** high-cardinality fields like `client_ip` or `request_id` from Prometheus labels, saving those specifically for the structured `aiops.log`.

## 3. Anomaly Design

The traffic generator orchestrates a controlled experiment lasting 10 minutes:
- **Base Load**: Distributes ~4000 total requests natively weighted towards healthy responses (70% normal, 15% slow, etc.).
- **Anomaly Window**: Lasts precisely 2 minutes. The anomaly profile used is an **Error Spike**, where the standard `/api/error` frequency jumps from 5% to ~40%.

Because the errors are tagged explicitly with the `SYSTEM_ERROR` and `TIMEOUT_ERROR` categories at the middleware layer, Grafana easily plots the stacked error rate. The visual spike proves the observability stack's capability to natively detect categorized layer-7 issues.

## 4. Visual Evidence & Deliverables

The observability stack successfully detected and categorized the orchestrated **Anomaly Spike**.

### 4.1. Dashboard Overview
![Full Dashboard](grafana_dashboard_full.png)
*The full dashboard view showing the transition from baseline traffic to the anomaly window (marked by the red region).*

### 4.2. Error Category Breakdown
![Error Categories](grafana_error_breakdown.png)
*Categorical breakdown proving that specific layer-7 failures (TIMEOUT_ERROR and SYSTEM_ERROR) are correctly identified and plotted.*

### 4.3. Error Rate % per Endpoint
![Error Rate](grafana_error_rate.png)
*Distribution of errors per target endpoint during the 2-minute anomaly spike.*

---

- **Logs Export**: `logs.json` successfully contains >3000 strictly structured request traces (surpassing the 1500 requirement) showing both success scenarios and various failure modes.
- **Ground Truth**: Accompanied `ground_truth.json` logs the exact ISO-8601 timestamps of the injected 2-minute anomaly spike to train or validate downstream models.
