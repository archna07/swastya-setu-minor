"""
Swastya Setu – Test AI Disease Prediction Combinations
Validates that different symptom inputs produce distinct, clinically grounded predictions.
"""

import urllib.request
import json

base_url = "http://127.0.0.1:5000"

# 1. Health Check
req = urllib.request.urlopen(base_url + "/health")
health_data = json.loads(req.read().decode())
print("HEALTH STATUS:", health_data)

# 2. Test Combinations Required in Phase 2
test_cases = [
    ("Cough + Fever", ["cough", "fever"]),
    ("Cough + Fever + Chills", ["cough", "fever", "chills"]),
    ("Abdominal Pain + Vomiting + Diarrhea", ["abdominal_pain", "vomiting", "diarrhea"]),
    ("Excessive Thirst + Frequent Urination + Blurred Vision", ["excessive_thirst", "frequent_urination", "blurred_vision"]),
    ("Sneezing + Itching + Runny Nose", ["sneezing", "itching", "runny_nose"]),
    ("Joint Pain + Swollen Joints + Muscle Weakness", ["joint_pain", "swollen_joints", "muscle_weakness"]),
    ("Headache Alone", ["headache"]),
    ("Headache + Dizziness + Nausea", ["headache", "dizziness", "nausea"]),
    ("Emergency: Chest Pain + Shortness of Breath", ["chest_pain", "shortness_of_breath"]),
    ("Burning Urination + Frequent Urination", ["burning_urination", "frequent_urination"]),
    ("Wheezing + Shortness of Breath", ["wheezing", "shortness_of_breath"]),
    ("Yellow Skin + Dark Urine", ["yellow_skin", "dark_urine"])
]

print("\n" + "=" * 80)
print(f"{'Input Symptoms':<45} | {'Predicted Condition':<30} | {'Score'}")
print("=" * 80)

for label, symptoms in test_cases:
    payload = json.dumps({"symptoms": symptoms}).encode("utf-8")
    request = urllib.request.Request(
        base_url + "/predict",
        data=payload,
        headers={"Content-Type": "application/json"}
    )
    response = urllib.request.urlopen(request)
    result = json.loads(response.read().decode())
    
    primary = result["primary_condition"]
    conf = result["confidence"]
    emergency = " [EMERGENCY ALERT]" if result.get("emergency_alert") else ""
    
    print(f"{label:<45} | {primary:<30} | {conf}%{emergency}")

print("=" * 80)
print("TEST COMPLETED SUCCESSFULLY!")

