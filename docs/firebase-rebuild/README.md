# Firebase リビルド向けドキュメント

このディレクトリは、現行 RAKUSHIRU Cloud の実装と既存ドキュメントを根拠に、Firebase で新システムを再構築する前提の要件定義・詳細設計を整理したものです。

> 依頼文の `firabese` は `Firebase` の意図として扱う。

## 文書一覧

| 文書 | 用途 |
| --- | --- |
| [requirements.md](requirements.md) | 現行機能を踏まえた新システム要件定義 |
| [detailed-design.md](detailed-design.md) | Firebase 構成、データ設計、画面/機能単位の詳細設計 |

## 前提

- 現行システムは Laravel 12 + Inertia/React + MySQL で構成されている。
- 新システムは Firebase を中心に、Auth / Firestore / Cloud Functions / Storage / Hosting の利用を想定する。
- Money Forward API 連携、顧客 API 連携、社員 API 連携は現行実装に存在するが、新システムでは同じ方式を前提にしない。
- 特に Money Forward API と顧客 API の連携方式は「新システムで再検討」とし、移行初期の確定仕様には含めない。

## 根拠にした主な現行資料

- `README.md`
- `docs/API_REFERENCE.md`
- `docs/MAINTENANCE_FEE.md`
- `docs/reference/README_ESTIMATE.md`
- `docs/reference/README_bill.md`
- `routes/web.php`
- `routes/api.php`
- `app/Services/MoneyForwardApiService.php`
- `app/Services/MoneyForwardQuoteSynchronizer.php`
- `app/Services/MoneyForwardBillingSynchronizer.php`
- `app/Services/MaintenanceFeeSyncService.php`
- `app/Services/ManagementMetricsService.php`
- `app/Services/BusinessDivisionAnalysisService.php`
- `app/Models/Estimate.php`
- `schema.dbml`
