"""
Swastya Setu – Machine Learning Model Training & Comparative Evaluation
Educational Prototype & Clinical Decision-Support Demonstration
"""

import os
import json
import joblib
import numpy as np
import pandas as pd
from sklearn.model_selection import train_test_split, StratifiedKFold, cross_val_score
from sklearn.ensemble import RandomForestClassifier, GradientBoostingClassifier
from sklearn.tree import DecisionTreeClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.naive_bayes import MultinomialNB
from sklearn.metrics import (
    accuracy_score, precision_recall_fscore_support, classification_report, confusion_matrix
)

# Set directories
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATASETS_DIR = os.path.join(BASE_DIR, "datasets")
os.makedirs(DATASETS_DIR, exist_ok=True)

CATALOG_PATH = os.path.join(BASE_DIR, "symptoms_catalog.json")

# 1. Load Standardized Symptoms and Disease Profiles Catalog
with open(CATALOG_PATH, "r", encoding="utf-8") as f:
    catalog = json.load(f)

SYMPTOM_ENTRIES = catalog["symptoms"]
SYMPTOM_IDS = [s["id"] for s in SYMPTOM_ENTRIES]
DISEASE_PROFILES = catalog["disease_profiles"]

print(f"[INFO] Loaded {len(SYMPTOM_IDS)} standardized symptoms and {len(DISEASE_PROFILES)} clinical disease profiles.")

# 2. Synthesize High-Fidelity Clinical Presentation Dataset
# Generates realistic patient cases with primary indicators, secondary presentations, and non-specific background noise.
np.random.seed(42)
SAMPLES_PER_DISEASE = 120
dataset_rows = []

for disease_name, profile in DISEASE_PROFILES.items():
    core_syms = profile.get("core_symptoms", [])
    secondary_syms = profile.get("secondary_symptoms", [])
    
    for sample_idx in range(SAMPLES_PER_DISEASE):
        row = {sym_id: 0 for sym_id in SYMPTOM_IDS}
        
        # Core symptoms have 85% - 98% occurrence probability
        for cs in core_syms:
            if cs in row and np.random.rand() < np.random.uniform(0.85, 0.98):
                row[cs] = 1
                
        # Secondary symptoms have 40% - 70% occurrence probability
        for ss in secondary_syms:
            if ss in row and np.random.rand() < np.random.uniform(0.40, 0.70):
                row[ss] = 1
                
        # Rare non-specific noise (e.g. slight fatigue or mild headache in 8% of cases)
        if np.random.rand() < 0.08:
            non_specific = np.random.choice(["fatigue", "body_ache", "headache", "loss_of_appetite"])
            if non_specific in row:
                row[non_specific] = 1
                
        # Ensure at least one core symptom is always active
        if sum(row.values()) == 0 and core_syms:
            row[core_syms[0]] = 1
            
        row["disease"] = disease_name
        dataset_rows.append(row)

df = pd.DataFrame(dataset_rows)
dataset_path = os.path.join(DATASETS_DIR, "Training.csv")
df.to_csv(dataset_path, index=False)
print(f"[INFO] Generated dataset with {len(df)} samples across {df['disease'].nunique()} conditions saved to {dataset_path}.")

# 3. Stratified Train / Test Split
X = df[SYMPTOM_IDS]
y = df["disease"]

X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.20, random_state=42, stratify=y
)
print(f"[INFO] Split data into {len(X_train)} training samples and {len(X_test)} hold-out test samples (stratified).")

# 4. Multi-Model Evaluation and Comparison
models = {
    "Random Forest": RandomForestClassifier(n_estimators=120, max_depth=16, min_samples_split=2, random_state=42),
    "Gradient Boosting": GradientBoostingClassifier(n_estimators=80, learning_rate=0.1, max_depth=5, random_state=42),
    "Decision Tree": DecisionTreeClassifier(max_depth=14, random_state=42),
    "Logistic Regression": LogisticRegression(max_iter=1000, random_state=42),
    "Multinomial Naive Bayes": MultinomialNB(alpha=1.0)
}

comparison_results = {}
skf = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)

for name, clf in models.items():
    # 5-Fold Stratified Cross Validation on Training Set
    cv_scores = cross_val_score(clf, X_train, y_train, cv=skf, scoring="f1_weighted")
    cv_mean = float(np.mean(cv_scores))
    cv_std = float(np.std(cv_scores))
    
    # Train and evaluate on hold-out test set
    clf.fit(X_train, y_train)
    y_pred = clf.predict(X_test)
    
    test_acc = float(accuracy_score(y_test, y_pred))
    p_w, r_w, f1_w, _ = precision_recall_fscore_support(y_test, y_pred, average="weighted", zero_division=0)
    p_m, r_m, f1_m, _ = precision_recall_fscore_support(y_test, y_pred, average="macro", zero_division=0)
    
    comparison_results[name] = {
        "model_instance": clf,
        "cv_f1_weighted_mean": round(cv_mean * 100, 2),
        "cv_f1_weighted_std": round(cv_std * 100, 2),
        "test_accuracy": round(test_acc * 100, 2),
        "test_precision_weighted": round(float(p_w) * 100, 2),
        "test_recall_weighted": round(float(r_w) * 100, 2),
        "test_f1_weighted": round(float(f1_w) * 100, 2),
        "test_f1_macro": round(float(f1_m) * 100, 2)
    }
    print(f"[MODEL] {name:24} | CV F1: {cv_mean*100:6.2f}% ± {cv_std*100:4.2f}% | Test F1: {f1_w*100:6.2f}% | Test Acc: {test_acc*100:6.2f}%")

# Select Champion Model (Random Forest or Gradient Boosting based on weighted F1)
champion_name = max(comparison_results.keys(), key=lambda k: comparison_results[k]["test_f1_weighted"])
champion_model = comparison_results[champion_name]["model_instance"]
print(f"\n[CHAMPION] Selected {champion_name} as the production model.")

# 5. Detailed Metrics for Champion Model
champion_model.fit(X_train, y_train)
y_pred_final = champion_model.predict(X_test)
report_dict = classification_report(y_test, y_pred_final, output_dict=True, zero_division=0)
conf_mat = confusion_matrix(y_test, y_pred_final, labels=champion_model.classes_)

# 6. Save Artifacts
MODEL_PATH = os.path.join(BASE_DIR, "disease_model.pkl")
SYMPTOMS_PATH = os.path.join(BASE_DIR, "symptoms_list.pkl")
METADATA_PATH = os.path.join(BASE_DIR, "metadata.json")
EVAL_PATH = os.path.join(BASE_DIR, "model_evaluation_report.json")

joblib.dump(champion_model, MODEL_PATH)
joblib.dump(SYMPTOM_IDS, SYMPTOMS_PATH)

# Build comprehensive metadata
metadata = {
    "version": catalog.get("version", "2.1.0"),
    "champion_model": champion_name,
    "total_symptoms": len(SYMPTOM_IDS),
    "total_diseases": len(DISEASE_PROFILES),
    "emergency_symptom_ids": catalog.get("emergency_symptom_ids", []),
    "disclaimer": catalog.get("disclaimer", ""),
    "diseases": DISEASE_PROFILES
}

with open(METADATA_PATH, "w", encoding="utf-8") as f:
    json.dump(metadata, f, indent=4)

# Build evaluation report
serializable_comparison = {}
for m_name, res in comparison_results.items():
    serializable_comparison[m_name] = {k: v for k, v in res.items() if k != "model_instance"}

evaluation_report = {
    "timestamp": pd.Timestamp.now().isoformat(),
    "dataset": {
        "samples_total": len(df),
        "samples_train": len(X_train),
        "samples_test": len(X_test),
        "symptoms_count": len(SYMPTOM_IDS),
        "diseases_count": len(DISEASE_PROFILES)
    },
    "model_comparison": serializable_comparison,
    "champion_model": {
        "name": champion_name,
        "classes": list(champion_model.classes_),
        "classification_report": report_dict,
        "confusion_matrix": conf_mat.tolist()
    },
    "clinical_disclosure": "Model is trained on primary care disease-symptom reference presentations for academic decision support. It is not an FDA/CDSCO approved medical device and requires independent clinical review before patient deployment."
}

with open(EVAL_PATH, "w", encoding="utf-8") as f:
    json.dump(evaluation_report, f, indent=4)

print(f"[SUCCESS] All model artifacts serialized:")
print(f"  - Model: {MODEL_PATH}")
print(f"  - Symptoms list: {SYMPTOMS_PATH}")
print(f"  - Metadata: {METADATA_PATH}")
print(f"  - Evaluation report: {EVAL_PATH}")
