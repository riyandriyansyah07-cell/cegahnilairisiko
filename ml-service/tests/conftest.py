import sys
from pathlib import Path

import numpy as np
import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

# profil: [kehadiran m, sd, IPS m, sd, rasio gagal min, maks, delta m, sd] (meniru DatabaseSeeder)
PROFILES = {
    "tinggi": (68, 8, 1.9, 0.35, 0.15, 0.5, -0.4, 0.3),
    "sedang": (79, 6, 2.45, 0.3, 0.0, 0.2, -0.1, 0.3),
    "rendah": (91, 5, 3.3, 0.3, 0.0, 0.05, 0.1, 0.25),
}


def synthetic_rows(n: int = 400, seed: int = 2026) -> list[dict]:
    rng = np.random.default_rng(seed)
    rows = []
    for _ in range(n):
        r = rng.integers(1, 101)
        label = "tinggi" if r <= 22 else ("sedang" if r <= 57 else "rendah")
        am, a_s, im, i_s, f0, f1, dm, ds = PROFILES[label]
        rows.append({
            "attendance": round(float(np.clip(rng.normal(am, a_s), 30, 100)), 2),
            "ips": round(float(np.clip(rng.normal(im, i_s), 0.5, 4)), 2),
            "failed_ratio": round(float(f0 + (f1 - f0) * rng.random()), 4),
            "ips_delta": round(float(np.clip(rng.normal(dm, ds), -3, 3)), 2),
            "label": label,
        })
    return rows


def split(rows):
    """Pola yang sama dengan ThresholdTrainer: indeks mod 10 < 7 masuk data latih."""
    train = [r for i, r in enumerate(rows) if i % 10 < 7]
    test = [r for i, r in enumerate(rows) if i % 10 >= 7]
    return train, test


@pytest.fixture(scope="session")
def data():
    return split(synthetic_rows())
