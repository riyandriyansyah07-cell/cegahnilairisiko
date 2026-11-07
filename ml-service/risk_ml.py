"""Inti pelatihan dan prediksi risiko akademik (layanan model, Pertemuan 6 bagian 2.2 dan UC-11).

Alur pelatihan:
1. Data latih dan data uji datang terpisah dari Laravel (data uji tidak dipakai untuk memilih apa pun).
2. Random Forest dan XGBoost dinilai dengan validasi silang bertingkat pada data latih.
3. Skor risiko 0-10 = 10 x (0,5 x P(sedang) + P(tinggi)). Dua ambang skor dikalibrasi pada
   prediksi out-of-fold agar kelas Tinggi yang jarang tidak terlewat.
4. Algoritma dengan nilai tujuan terbaik dilatih ulang pada seluruh data latih,
   lalu diuji satu kali pada data uji (macro-F1 dan recall kelas Tinggi).
5. Penjelasan faktor memakai SHAP (Lundberg & Lee, 2017) pada skor risiko.
"""

from __future__ import annotations

import re
from pathlib import Path
from typing import Any

import joblib
import numpy as np
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import StratifiedKFold
from sklearn.utils.class_weight import compute_sample_weight

FEATURES = ["attendance", "ips", "failed_ratio", "ips_delta"]
LEVELS = ["rendah", "sedang", "tinggi"]
SEED = 42
BACKGROUND_SIZE = 40
VERSION_RE = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]{0,39}$")

MODELS_DIR = Path(__file__).resolve().parent / "models"

_cache: dict[str, dict[str, Any]] = {}


class TrainingError(ValueError):
    """Data latih tidak layak dipakai. Pesannya ditampilkan ke admin."""


# ---------------------------------------------------------------- data

def to_xy(rows: list[dict]) -> tuple[np.ndarray, np.ndarray]:
    x = np.array([[float(r[f]) for f in FEATURES] for r in rows], dtype=float)
    y = np.array([LEVELS.index(r["label"]) for r in rows], dtype=int)
    return x, y


def risk_score(proba: np.ndarray) -> np.ndarray:
    """Skor 0-10 dari probabilitas kelas (rendah, sedang, tinggi)."""
    proba = np.asarray(proba, dtype=float)
    return 10.0 * (0.5 * proba[..., 1] + proba[..., 2])


def classify(score: float, thresholds: dict[str, float]) -> str:
    if score >= thresholds["tinggi"]:
        return "tinggi"
    return "sedang" if score >= thresholds["sedang"] else "rendah"


# ------------------------------------------------------------- metrik

def evaluate(actual: list[int], predicted: list[int]) -> dict:
    """Metrik tiga kelas dengan bentuk yang sama seperti App\\Support\\Metrics di Laravel."""
    confusion = {a: {p: 0 for p in LEVELS} for a in LEVELS}
    correct = 0
    for a, p in zip(actual, predicted):
        confusion[LEVELS[a]][LEVELS[p]] += 1
        correct += int(a == p)

    per_class: dict[str, dict] = {}
    f1_sum = 0.0
    for c in LEVELS:
        tp = confusion[c][c]
        fp = sum(confusion[o][c] for o in LEVELS if o != c)
        fn = sum(confusion[c][o] for o in LEVELS if o != c)
        precision = tp / (tp + fp) if tp + fp else 0.0
        recall = tp / (tp + fn) if tp + fn else 0.0
        f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
        per_class[c] = {
            "precision": round(precision, 4),
            "recall": round(recall, 4),
            "f1": round(f1, 4),
            "support": tp + fn,
        }
        f1_sum += f1

    n = len(actual)
    return {
        "confusion": confusion,
        "per_class": per_class,
        "macro_f1": round(f1_sum / len(LEVELS), 4),
        "recall_high": per_class["tinggi"]["recall"],
        "accuracy": round(correct / n, 4) if n else 0.0,
        "n": n,
    }


def objective(metrics: dict) -> float:
    """Tujuan yang sama dengan ThresholdTrainer di Laravel."""
    return metrics["macro_f1"] + 0.5 * metrics["recall_high"]


# ------------------------------------------------------------ model

def build(name: str):
    if name == "random_forest":
        return RandomForestClassifier(
            n_estimators=200, min_samples_leaf=3, class_weight="balanced",
            random_state=SEED, n_jobs=1,
        )
    if name == "xgboost":
        from xgboost import XGBClassifier

        return XGBClassifier(
            n_estimators=200, max_depth=3, learning_rate=0.08, subsample=0.9,
            colsample_bytree=0.9, objective="multi:softprob", eval_metric="mlogloss",
            tree_method="hist", random_state=SEED, n_jobs=1, verbosity=0,
        )
    raise ValueError(name)


def fit(name: str, x: np.ndarray, y: np.ndarray):
    model = build(name)
    if name == "xgboost":
        # XGBoost tidak punya class_weight: bobot sampel menyeimbangkan kelas Tinggi yang jarang.
        model.fit(x, y, sample_weight=compute_sample_weight("balanced", y))
    else:
        model.fit(x, y)
    return model


def oof_proba(name: str, x: np.ndarray, y: np.ndarray) -> np.ndarray:
    folds = max(2, min(3, int(np.bincount(y, minlength=3).min())))
    out = np.zeros((len(y), len(LEVELS)))
    for tr, va in StratifiedKFold(folds, shuffle=True, random_state=SEED).split(x, y):
        out[va] = fit(name, x[tr], y[tr]).predict_proba(x[va])
    return out


def calibrate(scores: np.ndarray, y: np.ndarray) -> tuple[dict[str, float], dict]:
    """Cari ambang sedang dan tinggi (pada skor 0-10) dengan tujuan terbaik."""
    best: tuple[float, dict[str, float], dict] | None = None
    s = 1.0
    while s <= 4.0:
        t = s + 0.5
        while t <= 8.0:
            th = {"sedang": s, "tinggi": t}
            pred = [LEVELS.index(classify(v, th)) for v in scores]
            m = evaluate(y.tolist(), pred)
            if best is None or objective(m) > best[0]:
                best = (objective(m), th, m)
            t += 0.25
        s += 0.25
    assert best is not None
    return best[1], best[2]


def predict_levels(model, x: np.ndarray, thresholds: dict[str, float]) -> list[int]:
    scores = risk_score(model.predict_proba(x))
    return [LEVELS.index(classify(v, thresholds)) for v in scores]


def check_data(train: list[dict], test: list[dict]) -> None:
    if len(train) < 30 or len(test) < 10:
        raise TrainingError("Data historis terlalu sedikit. Minimal 100 baris agar ada data latih dan data uji.")
    counts = {c: sum(1 for r in train if r["label"] == c) for c in LEVELS}
    for c, n in counts.items():
        if n < 5:
            raise TrainingError(f"Kelas {c} hanya {n} baris pada data latih. Minimal 5 baris per kelas.")


def train(version: str, train_rows: list[dict], test_rows: list[dict], models_dir: Path | None = None) -> dict:
    if not VERSION_RE.match(version):
        raise TrainingError("Nama versi tidak valid.")
    check_data(train_rows, test_rows)
    x_tr, y_tr = to_xy(train_rows)
    x_te, y_te = to_xy(test_rows)

    candidates: dict[str, dict] = {}
    for name in ("random_forest", "xgboost"):
        oof = oof_proba(name, x_tr, y_tr)
        thresholds, cv_metrics = calibrate(risk_score(oof), y_tr)
        model = fit(name, x_tr, y_tr)
        test_metrics = evaluate(y_te.tolist(), predict_levels(model, x_te, thresholds))
        candidates[name] = {
            "model": model,
            "thresholds": thresholds,
            "cv": cv_metrics,
            "test": test_metrics,
            "objective": objective(cv_metrics),
        }

    # Pemilihan hanya memakai validasi silang, sehingga data uji tetap jujur.
    chosen = max(candidates, key=lambda k: candidates[k]["objective"])
    pick = candidates[chosen]

    rng = np.random.default_rng(SEED)
    idx = rng.choice(len(x_tr), size=min(BACKGROUND_SIZE, len(x_tr)), replace=False)
    artifact = {
        "version": version,
        "algorithm": chosen,
        "model": pick["model"],
        "thresholds": pick["thresholds"],
        "features": FEATURES,
        "background": x_tr[idx],
    }
    target_dir = models_dir or MODELS_DIR
    target_dir.mkdir(parents=True, exist_ok=True)
    joblib.dump(artifact, target_dir / f"{version}.joblib")
    _cache.pop(str(target_dir / f"{version}.joblib"), None)

    importances = getattr(pick["model"], "feature_importances_", None)
    return {
        "version": version,
        "algorithm": chosen,
        "params": {"sedang": pick["thresholds"]["sedang"], "tinggi": pick["thresholds"]["tinggi"]},
        "metrics": pick["test"],
        "feature_importance": (
            {f: round(float(v), 4) for f, v in zip(FEATURES, importances)} if importances is not None else {}
        ),
        "candidates": {
            k: {
                "cv_objective": round(v["objective"], 4),
                "test_macro_f1": v["test"]["macro_f1"],
                "test_recall_high": v["test"]["recall_high"],
                "thresholds": v["thresholds"],
            }
            for k, v in candidates.items()
        },
        "train_rows": len(train_rows),
        "test_rows": len(test_rows),
    }


# --------------------------------------------------------- prediksi

def load(version: str, models_dir: Path | None = None) -> dict:
    if not VERSION_RE.match(version):
        raise FileNotFoundError(version)
    path = (models_dir or MODELS_DIR) / f"{version}.joblib"
    key = str(path)
    if key not in _cache:
        if not path.is_file():
            raise FileNotFoundError(version)
        art = joblib.load(path)
        _cache[key] = art
    return _cache[key]


def _explainer(art: dict):
    if "explainer" not in art:
        import shap

        model = art["model"]
        art["explainer"] = shap.KernelExplainer(
            lambda z: risk_score(model.predict_proba(np.asarray(z))), art["background"]
        )
    return art["explainer"]


def explain(art: dict, x: np.ndarray) -> np.ndarray:
    """Nilai SHAP per fitur pada skor risiko. Positif berarti menaikkan risiko.

    Dengan empat fitur, KernelSHAP menghitung semua koalisi secara eksak.
    """
    values = _explainer(art).shap_values(x, silent=True)
    return np.asarray(values, dtype=float).reshape(len(x), len(FEATURES))


def predict(version: str, instances: list[dict], models_dir: Path | None = None) -> list[dict]:
    art = load(version, models_dir)
    x = np.array([[float(i[f]) for f in FEATURES] for i in instances], dtype=float)
    proba = art["model"].predict_proba(x)
    scores = risk_score(proba)

    try:
        contrib = explain(art, x)
    except Exception:  # SHAP gagal: hasil tetap dikirim tanpa penjelasan, Laravel memakai penjelasan aturan.
        contrib = None

    out = []
    for i, score in enumerate(scores):
        score = float(np.clip(score, 0.0, 10.0))
        out.append({
            "score": round(score, 2),
            "level": classify(score, art["thresholds"]),
            "probabilities": {c: round(float(proba[i][k]), 4) for k, c in enumerate(LEVELS)},
            "contributions": (
                {f: round(float(contrib[i][k]), 4) for k, f in enumerate(FEATURES)} if contrib is not None else None
            ),
        })
    return out


def available_versions(models_dir: Path | None = None) -> list[str]:
    d = models_dir or MODELS_DIR
    return sorted(p.stem for p in d.glob("*.joblib")) if d.is_dir() else []
