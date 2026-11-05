"""
Swastya Setu – AI Service Unittest Test Suite
Unit and Integration tests for Flask AI Disease Prediction Microservice
Uses standard Python unittest module for zero-dependency execution.
"""

import sys
import os
import json
import unittest

# Add ai_service to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), "..", "ai_service"))

from app import app, load_artifacts

class AIServiceTestCase(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        load_artifacts()
        app.config["TESTING"] = True
        cls.client = app.test_client()

    def test_01_health_endpoint(self):
        """Verify /health returns 200 and indicates model is loaded."""
        response = self.client.get("/health")
        self.assertEqual(response.status_code, 200)
        data = json.loads(response.data)
        self.assertEqual(data["status"], "healthy")
        self.assertTrue(data["model_loaded"])
        self.assertGreater(data["total_symptoms"], 0)
        self.assertGreater(data["total_diseases"], 0)

    def test_02_symptoms_endpoint(self):
        """Verify /symptoms returns list of standardized symptoms with categories and labels."""
        response = self.client.get("/symptoms")
        self.assertEqual(response.status_code, 200)
        data = json.loads(response.data)
        self.assertEqual(data["status"], "success")
        self.assertGreaterEqual(len(data["symptoms"]), 30)
        self.assertIn("emergency_symptom_ids", data)

    def test_03_predict_single_symptom(self):
        """Verify /predict with a single symptom returns valid condition and score."""
        response = self.client.post(
            "/predict",
            data=json.dumps({"symptoms": ["headache"]}),
            content_type="application/json"
        )
        self.assertEqual(response.status_code, 200)
        data = json.loads(response.data)
        self.assertEqual(data["status"], "success")
        self.assertIn("primary_condition", data)
        self.assertIn("confidence", data)
        self.assertIn("precautions", data)
        self.assertIn("specialist", data)

    def test_04_predict_multiple_symptoms(self):
        """Verify multi-symptom clusters produce distinct diagnostic matches."""
        # Test Respiratory
        res1 = self.client.post("/predict", data=json.dumps({"symptoms": ["cough", "fever"]}), content_type="application/json")
        self.assertEqual(res1.status_code, 200)
        d1 = json.loads(res1.data)
        self.assertIn(d1["primary_condition"], ["Acute Bronchitis", "Common Cold", "Pneumonia"])

        # Test Metabolic
        res2 = self.client.post("/predict", data=json.dumps({"symptoms": ["excessive_thirst", "frequent_urination", "fatigue"]}), content_type="application/json")
        self.assertEqual(res2.status_code, 200)
        d2 = json.loads(res2.data)
        self.assertEqual(d2["primary_condition"], "Type 2 Diabetes Mellitus")

        # Test Gastrointestinal
        res3 = self.client.post("/predict", data=json.dumps({"symptoms": ["abdominal_pain", "diarrhea", "vomiting"]}), content_type="application/json")
        self.assertEqual(res3.status_code, 200)
        d3 = json.loads(res3.data)
        self.assertEqual(d3["primary_condition"], "Gastroenteritis (Food Poisoning)")

    def test_05_predict_emergency_trigger(self):
        """Verify emergency red-flag symptoms trigger emergency alert and guidance."""
        response = self.client.post(
            "/predict",
            data=json.dumps({"symptoms": ["chest_pain", "shortness_of_breath"]}),
            content_type="application/json"
        )
        self.assertEqual(response.status_code, 200)
        data = json.loads(response.data)
        self.assertTrue(data["emergency_alert"])
        self.assertIsNotNone(data["emergency_guidance"])
        self.assertIn("108", data["emergency_guidance"])

    def test_06_predict_empty_symptoms(self):
        """Verify empty symptoms payload returns HTTP 400 error."""
        response = self.client.post(
            "/predict",
            data=json.dumps({"symptoms": []}),
            content_type="application/json"
        )
        self.assertEqual(response.status_code, 400)
        data = json.loads(response.data)
        self.assertEqual(data["status"], "error")

    def test_07_predict_invalid_json(self):
        """Verify malformed content-type or non-JSON returns HTTP 400 error."""
        response = self.client.post(
            "/predict",
            data="not json string",
            content_type="text/plain"
        )
        self.assertEqual(response.status_code, 400)

    def test_08_predict_unknown_symptoms(self):
        """Verify unknown symptoms are handled gracefully with informative status."""
        response = self.client.post(
            "/predict",
            data=json.dumps({"symptoms": ["alien_tentacle_spasm", "crypto_fever"]}),
            content_type="application/json"
        )
        self.assertEqual(response.status_code, 200)
        data = json.loads(response.data)
        self.assertEqual(data["status"], "insufficient_information")

    def test_09_score_explanation_presence(self):
        """Verify model output includes plain-language explanation of model scores."""
        response = self.client.post(
            "/predict",
            data=json.dumps({"symptoms": ["sneezing", "runny_nose", "itching"]}),
            content_type="application/json"
        )
        self.assertEqual(response.status_code, 200)
        data = json.loads(response.data)
        self.assertIn("score_explanation", data)
        self.assertTrue(
            "decision-support" in data["score_explanation"].lower() or 
            "statistical" in data["score_explanation"].lower() or 
            "affinity" in data["score_explanation"].lower()
        )

if __name__ == "__main__":
    unittest.main(verbosity=2)
