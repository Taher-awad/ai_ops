import time
import random
import requests
import json
import threading
from datetime import datetime, timezone, timedelta

BASE_URL = "http://localhost:8000"
rps_base = 6
rps_anomaly = 10

def make_request(endpoint_type):
    try:
        if endpoint_type == 'normal':
            requests.get(f"{BASE_URL}/api/normal", timeout=3)
        elif endpoint_type == 'slow':
            requests.get(f"{BASE_URL}/api/slow", timeout=5)
        elif endpoint_type == 'hard_slow':
            requests.get(f"{BASE_URL}/api/slow?hard=1", timeout=8)
        elif endpoint_type == 'error':
            requests.get(f"{BASE_URL}/api/error", timeout=3)
        elif endpoint_type == 'db_ok':
            requests.get(f"{BASE_URL}/api/db", timeout=3)
        elif endpoint_type == 'db_fail':
            requests.get(f"{BASE_URL}/api/db?fail=1", timeout=3)
        elif endpoint_type == 'validate_ok':
            requests.post(f"{BASE_URL}/api/validate", json={"email": "test@example.com", "age": 25}, timeout=3)
        elif endpoint_type == 'validate_fail':
            requests.post(f"{BASE_URL}/api/validate", json={"email": "invalid", "age": 0}, timeout=3)
    except Exception:
        pass

def select_endpoint(anomaly=False):
    rand = random.uniform(0, 100)
    
    if anomaly:
        # Error spike: raise /api/error to ~40%
        # Normal 35%, Error 40%, Slow 15%, Hard Slow 5%, DB 3%, Validate 2%
        if rand < 35: return 'normal'
        elif rand < 75: return 'error' # 40% error
        elif rand < 90: return 'slow'
        elif rand < 95: return 'hard_slow'
        elif rand < 98: return 'db_ok' if random.random() > 0.5 else 'db_fail'
        else: return 'validate_ok' if random.random() > 0.5 else 'validate_fail'
    else:
        # Normal distribution
        # 70% normal, 15% slow, 5% hard_slow, 5% error, 3% db, 2% validate
        if rand < 70: return 'normal'
        elif rand < 85: return 'slow'
        elif rand < 90: return 'hard_slow'
        elif rand < 95: return 'error'
        elif rand < 98: return 'db_ok' if random.random() > 0.5 else 'db_fail'
        else: return 'validate_ok' if random.random() > 0.5 else 'validate_fail'


def run_phase(duration_secs, anomaly=False):
    start = time.time()
    count = 0
    while time.time() - start < duration_secs:
        endpoint = select_endpoint(anomaly)
        threading.Thread(target=make_request, args=(endpoint,)).start()
        
        sleep_time = 1.0 / (rps_anomaly if anomaly else rps_base)
        sleep_time *= random.uniform(0.8, 1.2) # Jitter
        time.sleep(sleep_time)
        count += 1
    return count

print("Starting Traffic Generator...")

# Phase 1: Base load (4 minutes) 
# Note: Instructions say Base Load (8-12 mins) total. I'll do 4 min base, 2 min anomaly, 4 min base = 10 min total.
# 10 mins at 6-10 rps = ~4000+ requests.
print("Phase 1: Base Load (4 minutes)")
run_phase(4 * 60, anomaly=False)

anomaly_start = datetime.now(timezone.utc)
print(f"Phase 2: Anomaly Window (2 minutes) starts at {anomaly_start.isoformat()}")

run_phase(2 * 60, anomaly=True)

anomaly_end = datetime.now(timezone.utc)
print(f"Anomaly Window ended at {anomaly_end.isoformat()}")

print("Phase 3: Base Load (4 minutes)")
run_phase(4 * 60, anomaly=False)

ground_truth = {
    "anomaly_start_iso": anomaly_start.isoformat(),
    "anomaly_end_iso": anomaly_end.isoformat(),
    "anomaly_type": "error_spike",
    "expected_behavior": "Simulated an error spike by raising /api/error request percentage to 40%."
}

with open("ground_truth.json", "w") as f:
    json.dump(ground_truth, f, indent=4)

print("Traffic generator finished. Metadata written to ground_truth.json.")
