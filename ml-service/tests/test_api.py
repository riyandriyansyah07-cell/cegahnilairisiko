import pytest
from fastapi.testclient import TestClient

import main
import risk_ml


@pytest.fixture()
def client(tmp_path, monkeypatch):
    monkeypatch.setattr(risk_ml, "MODELS_DIR", tmp_path)
    monkeypatch.setenv("ML_ENV", "testing")
    monkeypatch.delenv("RISK_SERVICE_TOKEN", raising=False)
    return TestClient(main.app)


def payload(data, version="api-v1"):
    train, test = data
    return {"version": version, "train": train, "test": test}


def test_health(client):
    assert client.get("/health").json()["status"] == "ok"


def test_latih_lalu_prediksi(client, data):
    r = client.post("/train", json=payload(data))
    assert r.status_code == 200, r.text
    body = r.json()
    assert body["metrics"]["recall_high"] >= 0.8

    p = client.post("/predict", json={"version": "api-v1", "instances": [
        {"attendance": 60, "ips": 1.6, "failed_ratio": 0.4, "ips_delta": -0.6},
        {"attendance": 95, "ips": 3.6, "failed_ratio": 0, "ips_delta": 0.2},
    ]})
    assert p.status_code == 200, p.text
    res = p.json()["results"]
    assert [x["level"] for x in res] == ["tinggi", "rendah"]
    assert set(res[0]["contributions"]) == {"attendance", "ips", "failed_ratio", "ips_delta"}
    assert "api-v1" in client.get("/health").json()["versions"]


def test_prediksi_versi_tak_ada_404(client):
    r = client.post("/predict", json={"version": "nol", "instances": [
        {"attendance": 80, "ips": 3, "failed_ratio": 0, "ips_delta": 0}]})
    assert r.status_code == 404


def test_validasi_rentang_dan_label(client, data):
    bad = {"attendance": 150, "ips": 3, "failed_ratio": 0, "ips_delta": 0}
    assert client.post("/predict", json={"version": "x", "instances": [bad]}).status_code == 422
    p = payload(data)
    p["train"] = p["train"][:50] + [{**p["train"][0], "label": "aneh"}]
    assert client.post("/train", json=p).status_code == 422


def test_data_sedikit_ditolak_422_dengan_pesan(client, data):
    p = payload(data)
    p["train"] = p["train"][:10]
    r = client.post("/train", json=p)
    assert r.status_code == 422 and "terlalu sedikit" in r.json()["detail"]


def test_token_layanan(client, data, monkeypatch):
    inst = {"version": "x", "instances": [{"attendance": 80, "ips": 3, "failed_ratio": 0, "ips_delta": 0}]}

    monkeypatch.setenv("ML_ENV", "production")
    monkeypatch.delenv("RISK_SERVICE_TOKEN", raising=False)
    assert client.post("/predict", json=inst).status_code == 401

    monkeypatch.setenv("RISK_SERVICE_TOKEN", "rahasia")
    assert client.post("/predict", json=inst).status_code == 401
    assert client.post("/predict", json=inst, headers={"X-Service-Token": "salah"}).status_code == 401
    # token benar lolos autentikasi; versi tidak ada -> 404, bukan 401
    assert client.post("/predict", json=inst, headers={"X-Service-Token": "rahasia"}).status_code == 404
