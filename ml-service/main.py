"""Layanan model prediksi (FastAPI). Hanya menerima fitur numerik tanpa identitas.

Jalankan:  uvicorn main:app --host 127.0.0.1 --port 8001
"""

from __future__ import annotations

import hmac
import os
from typing import Literal

from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel, Field

import risk_ml

app = FastAPI(title="Layanan model risiko akademik", version="1.0")

DEV_ENVS = {"local", "dev", "development", "testing", "test"}


def require_token(x_service_token: str | None = Header(default=None)) -> None:
    expected = os.environ.get("RISK_SERVICE_TOKEN", "").strip()
    env = os.environ.get("ML_ENV", os.environ.get("APP_ENV", "local")).strip().lower()
    if not expected:
        if env not in DEV_ENVS:
            raise HTTPException(
                status_code=401,
                detail="RISK_SERVICE_TOKEN wajib dikonfigurasi di luar mode pengembangan.",
            )
        return
    if not hmac.compare_digest(expected, x_service_token or ""):
        raise HTTPException(status_code=401, detail="Token layanan tidak valid.")


class Features(BaseModel):
    attendance: float = Field(ge=0, le=100)
    ips: float = Field(ge=0, le=4)
    failed_ratio: float = Field(ge=0, le=1)
    ips_delta: float = Field(ge=-4, le=4)


class LabeledRow(Features):
    label: Literal["rendah", "sedang", "tinggi"]


class TrainRequest(BaseModel):
    version: str
    train: list[LabeledRow] = Field(max_length=5000)
    test: list[LabeledRow] = Field(max_length=5000)


class PredictRequest(BaseModel):
    version: str
    instances: list[Features] = Field(min_length=1, max_length=50)


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "versions": risk_ml.available_versions()}


@app.post("/train", dependencies=[Depends(require_token)])
def train(req: TrainRequest) -> dict:
    try:
        return risk_ml.train(
            req.version,
            [r.model_dump() for r in req.train],
            [r.model_dump() for r in req.test],
        )
    except risk_ml.TrainingError as e:
        raise HTTPException(status_code=422, detail=str(e)) from e


@app.post("/predict", dependencies=[Depends(require_token)])
def predict(req: PredictRequest) -> dict:
    try:
        results = risk_ml.predict(req.version, [i.model_dump() for i in req.instances])
    except FileNotFoundError as e:
        raise HTTPException(status_code=404, detail="Versi model tidak ditemukan di layanan.") from e
    return {"version": req.version, "results": results}
