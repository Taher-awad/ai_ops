#!/bin/bash

# Configuration
GRAFANA_URL="http://localhost:3000"
USER_PASS="admin:admin"

echo "Waiting for Grafana to be up..."
until curl -s $GRAFANA_URL/api/health | grep '"database": "ok"' > /dev/null; do
  sleep 1
done

echo "Adding Prometheus Data Source..."
curl -s -X POST $GRAFANA_URL/api/datasources \
  -u "$USER_PASS" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Prometheus",
    "type": "prometheus",
    "url": "http://prometheus:9090",
    "access": "proxy",
    "isDefault": true
  }'

echo "Creating Dashboard..."
DASHBOARD_JSON=$(cat << 'EOF'
{
  "dashboard": {
    "id": null,
    "uid": "aiops-telemetry",
    "title": "AIOps Telemetry Dashboard",
    "tags": [ "templated" ],
    "timezone": "browser",
    "schemaVersion": 36,
    "version": 0,
    "refresh": "5s",
    "panels": [
      {
        "type": "timeseries",
        "title": "Request Rate (RPS)",
        "gridPos": { "h": 8, "w": 12, "x": 0, "y": 0 },
        "targets": [
          {
            "expr": "rate(http_requests_total[1m])",
            "legendFormat": "{{method}} {{path}}"
          }
        ]
      },
      {
        "type": "timeseries",
        "title": "Error Rate % per Endpoint",
        "gridPos": { "h": 8, "w": 12, "x": 12, "y": 0 },
        "targets": [
          {
            "expr": "sum by (path) (rate(http_errors_total[1m])) / sum by (path) (rate(http_requests_total[1m])) * 100",
            "legendFormat": "{{path}} Error %"
          }
        ],
        "fieldConfig": {
          "defaults": { "unit": "percent" }
        }
      },
      {
        "type": "timeseries",
        "title": "Latency (P50/P95/P99)",
        "gridPos": { "h": 8, "w": 12, "x": 0, "y": 8 },
        "targets": [
          {
            "expr": "histogram_quantile(0.99, sum by (le, path) (rate(http_request_duration_seconds_bucket[1m])))",
            "legendFormat": "P99 {{path}}"
          },
          {
            "expr": "histogram_quantile(0.95, sum by (le, path) (rate(http_request_duration_seconds_bucket[1m])))",
            "legendFormat": "P95 {{path}}"
          },
          {
            "expr": "histogram_quantile(0.50, sum by (le, path) (rate(http_request_duration_seconds_bucket[1m])))",
            "legendFormat": "P50 {{path}}"
          }
        ],
        "fieldConfig": {
          "defaults": { "unit": "s" }
        }
      },
      {
        "type": "timeseries",
        "title": "Error Category Breakdown",
        "gridPos": { "h": 8, "w": 12, "x": 12, "y": 8 },
        "targets": [
          {
            "expr": "sum by (error_category) (rate(http_errors_total[1m]))",
            "legendFormat": "{{error_category}}"
          }
        ],
        "custom": {
          "stacking": { "mode": "normal", "group": "A" }
        }
      }
    ],
    "annotations": {
      "list": [
        {
          "name": "Anomaly Spike",
          "datasource": { "type": "prometheus", "uid": "prometheus" },
          "enable": true,
          "expr": "sum(rate(http_errors_total{error_category=\"SYSTEM_ERROR\"}[1m])) > 3",
          "step": "10s",
          "iconColor": "rgba(255, 96, 96, 1)"
        }
      ]
    }
  },
  "overwrite": true
}
EOF
)

curl -s -X POST $GRAFANA_URL/api/dashboards/db \
  -u "$USER_PASS" \
  -H "Content-Type: application/json" \
  -d "$DASHBOARD_JSON" > /dev/null

echo "Dashboard provisioned."
