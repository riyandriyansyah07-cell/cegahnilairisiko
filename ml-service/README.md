# Layanan model risiko akademik

FastAPI + scikit-learn + XGBoost + SHAP. Dipanggil Laravel lewat REST internal (Pertemuan 6, bagian 2.3).
Hanya menerima fitur numerik tanpa identitas. Jalankan di 127.0.0.1, jangan dibuka ke internet.

```bash
pip install -r requirements.txt
RISK_SERVICE_TOKEN=rahasia uvicorn main:app --host 127.0.0.1 --port 8001   # token opsional
python -m pytest
```

| Endpoint | Fungsi |
|---|---|
| `GET /health` | status dan daftar versi tersimpan |
| `POST /train` | `{version, train[], test[]}`: melatih Random Forest dan XGBoost, memilih lewat validasi silang, menguji pada `test`, menyimpan `models/<version>.joblib` |
| `POST /predict` | `{version, instances[]}`: hasilnya `{results:[{score, level, probabilities, contributions}]}`. `contributions` adalah nilai SHAP per fitur pada skor risiko |

Baris data: `attendance` (0-100), `ips` (0-4), `failed_ratio` (0-1), `ips_delta` (-4 sampai 4), dan `label`
(`rendah`, `sedang`, `tinggi`) pada data latih dan uji. Berkas model tersimpan di `models/` dan tidak masuk repositori.
