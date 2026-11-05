"""
Swastya Setu – AI Disease Prediction Microservice
Production-Hardened Flask REST API for Clinical Decision Support
"""

import os
import sys
import json
import logging
import joblib
import numpy as np
from flask import Flask, request, jsonify
from flask_cors import CORS

# Configure logging (no patient health info logged)
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
    handlers=[logging.StreamHandler(sys.stdout)]
)
logger = logging.getLogger("SwastyaSetuAI")

app = Flask(__name__)

# Request payload limit: 50 KB max to prevent DoS
app.config["MAX_CONTENT_LENGTH"] = 50 * 1024

# CORS: Allow local application frontend origins
CORS(app, resources={r"/*": {"origins": ["http://localhost:8000", "http://127.0.0.1:8000", "http://localhost", "http://127.0.0.1"]}})

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, "disease_model.pkl")
SYMPTOMS_PATH = os.path.join(BASE_DIR, "symptoms_list.pkl")
METADATA_PATH = os.path.join(BASE_DIR, "metadata.json")
CATALOG_PATH = os.path.join(BASE_DIR, "symptoms_catalog.json")

# Model and Artifacts Loading
model = None
symptoms_list = []
catalog_data = {}
metadata = {}
emergency_ids = set()

def load_artifacts():
    global model, symptoms_list, catalog_data, metadata, emergency_ids
    try:
        if os.path.exists(MODEL_PATH):
            model = joblib.load(MODEL_PATH)
            logger.info("Loaded machine learning model successfully.")
        else:
            logger.error("Model file not found at %s", MODEL_PATH)

        if os.path.exists(SYMPTOMS_PATH):
            symptoms_list = joblib.load(SYMPTOMS_PATH)
            logger.info("Loaded %d model input symptoms.", len(symptoms_list))
        else:
            logger.error("Symptoms list file not found at %s", SYMPTOMS_PATH)

        if os.path.exists(CATALOG_PATH):
            with open(CATALOG_PATH, "r", encoding="utf-8") as f:
                catalog_data = json.load(f)
            emergency_ids = set(catalog_data.get("emergency_symptom_ids", []))
            logger.info("Loaded catalog data with %d emergency symptom indicators.", len(emergency_ids))
        elif os.path.exists(METADATA_PATH):
            with open(METADATA_PATH, "r", encoding="utf-8") as f:
                metadata = json.load(f)
            emergency_ids = set(metadata.get("emergency_symptom_ids", []))

        if os.path.exists(METADATA_PATH):
            with open(METADATA_PATH, "r", encoding="utf-8") as f:
                metadata = json.load(f)
    except Exception as e:
        logger.exception("Failed to load model artifacts: %s", str(e))

load_artifacts()

# Symptom normalization helper
def normalize_symptom_key(key_str):
    if not isinstance(key_str, str):
        return ""
    clean = key_str.strip().lower().replace("-", "_").replace(" ", "_")
    # Clean non-alphanumeric chars except underscore
    return "".join(c for c in clean if c.isalnum() or c == "_")

@app.after_request
def add_security_headers(response):
    response.headers["X-Content-Type-Options"] = "nosniff"
    response.headers["X-Frame-Options"] = "DENY"
    response.headers["X-XSS-Protection"] = "1; mode=block"
    return response

@app.errorhandler(400)
def bad_request(e):
    return jsonify({"status": "error", "message": "Bad Request: " + str(e)}), 400

@app.errorhandler(404)
def not_found(e):
    return jsonify({"status": "error", "message": "Endpoint Not Found"}), 404

@app.errorhandler(413)
def request_entity_too_large(e):
    return jsonify({"status": "error", "message": "Request payload exceeds allowed limit (50KB)."}), 413

@app.errorhandler(500)
def internal_server_error(e):
    return jsonify({"status": "error", "message": "Internal AI Service Error"}), 500

@app.route("/health", methods=["GET"])
def health():
    return jsonify({
        "status": "healthy",
        "service": "Swastya Setu AI Disease Prediction Engine",
        "version": metadata.get("version", "2.1.0"),
        "champion_model": metadata.get("champion_model", "Random Forest Classifier"),
        "model_loaded": model is not None,
        "total_symptoms": len(symptoms_list),
        "total_diseases": len(metadata.get("diseases", {}))
    })

@app.route("/symptoms", methods=["GET"])
def get_symptoms():
    symptoms_catalog = catalog_data.get("symptoms", [])
    if not symptoms_catalog and symptoms_list:
        # Fallback if catalog not available
        symptoms_catalog = [{"id": s, "label": s.replace("_", " ").title(), "category": "General", "is_emergency": s in emergency_ids} for s in symptoms_list]

    return jsonify({
        "status": "success",
        "total": len(symptoms_catalog),
        "symptoms": symptoms_catalog,
        "emergency_symptom_ids": list(emergency_ids)
    })

@app.route("/predict", methods=["POST"])
def predict():
    if model is None:
        return jsonify({
            "status": "error",
            "message": "AI model is not currently loaded. Please ensure artifacts are generated."
        }), 503

    if not request.is_json:
        return jsonify({
            "status": "error",
            "message": "Invalid request content-type. Expected application/json."
        }), 400

    data = request.get_json(silent=True)
    if not data or not isinstance(data, dict):
        return jsonify({
            "status": "error",
            "message": "Invalid JSON payload."
        }), 400

    raw_symptoms = data.get("symptoms", [])
    if not isinstance(raw_symptoms, list) or len(raw_symptoms) == 0:
        return jsonify({
            "status": "error",
            "message": "Please select at least one symptom to run the AI prediction."
        }), 400

    # Normalize symptoms
    normalized_input = [normalize_symptom_key(s) for s in raw_symptoms if s]
    valid_selected = [s for s in normalized_input if s in symptoms_list]

    # Check for Emergency Red-Flag Symptoms
    detected_emergency_symptoms = [s for s in normalized_input if s in emergency_ids]
    has_emergency = len(detected_emergency_symptoms) > 0

    if len(valid_selected) == 0:
        return jsonify({
            "status": "insufficient_information",
            "message": "The symptoms provided do not match known primary care features. Please select standard clinical symptoms.",
            "emergency_alert": has_emergency,
            "emergency_symptoms": detected_emergency_symptoms,
            "emergency_guidance": "Critical emergency symptoms were reported. Please contact emergency services (108) immediately." if has_emergency else None
        }), 200

    # Construct strict feature vector as pandas DataFrame with identical column names
    import pandas as pd
    input_vector = np.zeros(len(symptoms_list))
    for idx, sym_id in enumerate(symptoms_list):
        if sym_id in valid_selected:
            input_vector[idx] = 1.0
    input_df = pd.DataFrame([input_vector], columns=symptoms_list)

    # Model inference
    try:
        probabilities = model.predict_proba(input_df)[0]
        classes = model.classes_
    except Exception as e:
        logger.exception("Error during model prediction inference: %s", str(e))
        return jsonify({"status": "error", "message": "Inference computation failed."}), 500

    # Sort classes by probability descending
    sorted_indices = np.argsort(probabilities)[::-1]
    
    # Filter conditions that have meaningful probability (> 5%)
    top_candidates = []
    for idx in sorted_indices:
        prob = float(probabilities[idx])
        if prob >= 0.05:
            top_candidates.append((classes[idx], prob))
        if len(top_candidates) >= 3:
            break

    # If no condition reached 5%, take top 1 with low confidence warning
    if not top_candidates:
        top_candidates = [(classes[sorted_indices[0]], float(probabilities[sorted_indices[0]]))]

    diseases_metadata = metadata.get("diseases", {})
    formatted_predictions = []
    
    for rank, (disease_name, prob_val) in enumerate(top_candidates, start=1):
        info = diseases_metadata.get(disease_name, {})
        formatted_predictions.append({
            "rank": rank,
            "condition": disease_name,
            "probability": round(prob_val * 100, 1),
            "specialist": info.get("specialist", "General Physician"),
            "description": info.get("description", "A medical condition with associated clinical symptoms."),
            "precautions": info.get("precautions", [
                "Maintain adequate hydration and rest",
                "Consult a qualified physician for clinical assessment",
                "Monitor your symptoms and report any sudden worsening"
            ]),
            "associated_symptoms": info.get("core_symptoms", [])
        })

    primary = formatted_predictions[0]

    # Clinical explanation of model score
    score_explanation = (
        f"Statistical model affinity of {primary['probability']}% based on submitted symptom presentation. "
        "This score reflects pattern similarity within the decision-support prototype dataset, not a clinical probability."
    )

    response_payload = {
        "status": "success",
        "primary_condition": primary["condition"],
        "confidence": primary["probability"],
        "score_explanation": score_explanation,
        "specialist": primary["specialist"],
        "description": primary["description"],
        "precautions": primary["precautions"],
        "associated_symptoms": primary["associated_symptoms"],
        "top_predictions": formatted_predictions,
        "matched_symptoms_count": len(valid_selected),
        "emergency_alert": has_emergency,
        "emergency_symptoms": detected_emergency_symptoms,
        "emergency_guidance": (
            "URGENT: One or more selected symptoms (e.g. Chest Pain, Shortness of Breath) are potential emergency indicators. "
            "Please seek immediate medical evaluation or call 108 emergency ambulance service without delay."
        ) if has_emergency else None,
        "disclaimer": (
            "IMPORTANT MEDICAL NOTICE: This AI prediction is generated solely for informational decision support "
            "and does NOT constitute a clinical diagnosis or treatment prescription. Please consult a registered medical doctor."
        )
    }

    return jsonify(response_payload), 200

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    logger.info("Starting Swastya Setu Flask service on port %d...", port)
    app.run(host="127.0.0.1", port=port, debug=False)
