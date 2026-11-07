import numpy as np
import pytest

import risk_ml


@pytest.fixture(scope="module")
def trained(data, tmp_path_factory):
    d = tmp_path_factory.mktemp("models")
    train, test = data
    result = risk_ml.train("uji-v1", train, test, models_dir=d)
    return result, d


def test_pelatihan_memenuhi_target_nfr02(trained):
    result, _ = trained
    assert result["algorithm"] in {"random_forest", "xgboost"}
    assert result["metrics"]["macro_f1"] >= 0.70
    assert result["metrics"]["recall_high"] >= 0.80
    assert result["train_rows"] == 280 and result["test_rows"] == 120
    assert set(result["candidates"]) == {"random_forest", "xgboost"}


def test_metrik_dihitung_pada_data_uji(trained, data):
    result, _ = trained
    assert result["metrics"]["n"] == len(data[1])
    total = sum(sum(row.values()) for row in result["metrics"]["confusion"].values())
    assert total == len(data[1])
    assert 0 < result["params"]["sedang"] < result["params"]["tinggi"] <= 8


def test_prediksi_urutan_risiko_masuk_akal(trained):
    _, d = trained
    aman = {"attendance": 95, "ips": 3.6, "failed_ratio": 0, "ips_delta": 0.2}
    bahaya = {"attendance": 60, "ips": 1.6, "failed_ratio": 0.4, "ips_delta": -0.6}
    a, b = risk_ml.predict("uji-v1", [aman, bahaya], models_dir=d)
    assert a["level"] == "rendah" and b["level"] == "tinggi"
    assert a["score"] < b["score"]
    assert 0 <= a["score"] <= 10 and 0 <= b["score"] <= 10
    assert sum(b["probabilities"].values()) == pytest.approx(1, abs=1e-3)


def test_shap_aditif_dan_faktor_terbesar_benar(trained):
    _, d = trained
    art = risk_ml.load("uji-v1", d)
    x = np.array([[60, 1.6, 0.4, -0.6], [95, 3.6, 0, 0.2]], dtype=float)
    contrib = risk_ml.explain(art, x)
    base = float(np.mean(risk_ml.risk_score(art["model"].predict_proba(art["background"]))))
    scores = risk_ml.risk_score(art["model"].predict_proba(x))
    # SHAP aditif: dasar + jumlah kontribusi = skor
    assert contrib.sum(axis=1) + base == pytest.approx(scores, abs=0.05)
    # mahasiswa berisiko: tidak ada fitur yang menurunkan skor secara berarti pada kehadiran dan IPS
    assert contrib[0][0] > 0 and contrib[0][1] > 0
    # mahasiswa aman: kontribusi total negatif
    assert contrib[1].sum() < 0


def test_versi_tak_valid_atau_tak_ada(trained):
    _, d = trained
    with pytest.raises(FileNotFoundError):
        risk_ml.load("../etc/passwd", d)
    with pytest.raises(FileNotFoundError):
        risk_ml.load("tidak-ada", d)


def test_data_terlalu_sedikit_atau_kelas_hilang(data, tmp_path):
    train, test = data
    with pytest.raises(risk_ml.TrainingError):
        risk_ml.train("kecil", train[:20], test, models_dir=tmp_path)
    tanpa_tinggi = [r for r in train if r["label"] != "tinggi"]
    with pytest.raises(risk_ml.TrainingError, match="tinggi"):
        risk_ml.train("hilang", tanpa_tinggi, test, models_dir=tmp_path)
    with pytest.raises(risk_ml.TrainingError):
        risk_ml.train("../buruk", train, test, models_dir=tmp_path)


def test_hasil_deterministik(data, tmp_path):
    train, test = data
    a = risk_ml.train("a", train, test, models_dir=tmp_path)
    b = risk_ml.train("b", train, test, models_dir=tmp_path)
    assert a["metrics"] == b["metrics"] and a["params"] == b["params"]
