# Demo run: `./scripts/demo.sh` (full lifecycle + error cases)

```text
===== 1. Register client + freelancer =====
### POST /register
{
  "name": "Ada Client",
  "email": "ada+1792583258@example.com",
  "role": "client",
  "kyc": {
    "legal_full_name": "Ada Lovelace",
    "country": "GB",
    "government_id_type": "passport",
    "government_id_number": "P12345678",
    "date_of_birth": "1990-12-10"
  }
}
--> HTTP 201
{
  "data": {
    "id": 1,
    "name": "Ada Client",
    "email": "ada+1792583258@example.com",
    "role": "client",
    "kyc": {
      "legal_full_name": "Ada Lovelace",
      "country": "GB",
      "government_id_type": "passport",
      "government_id_number": "*****5678",
      "date_of_birth": "1990-12-10",
      "status": "Verified",
      "verified_at": "2026-10-21T11:47:38.000000Z"
    },
    "wallet": {
      "currency": "USD",
      "available_balance": 0,
      "escrow_balance": 0
    },
    "created_at": "2026-10-21T11:47:38.000000Z"
  }
}

### POST /register
{
  "name": "Linus Dev",
  "email": "linus+1792583258@example.com",
  "role": "freelancer",
  "kyc": {
    "legal_full_name": "Linus Torvalds",
    "country": "FI",
    "government_id_type": "national_id",
    "government_id_number": "FI-998877"
  }
}
--> HTTP 201
{
  "data": {
    "id": 2,
    "name": "Linus Dev",
    "email": "linus+1792583258@example.com",
    "role": "freelancer",
    "kyc": {
      "legal_full_name": "Linus Torvalds",
      "country": "FI",
      "government_id_type": "national_id",
      "government_id_number": "*****8877",
      "date_of_birth": null,
      "status": "Verified",
      "verified_at": "2026-10-21T11:47:39.000000Z"
    },
    "wallet": {
      "currency": "USD",
      "available_balance": 0,
      "escrow_balance": 0
    },
    "created_at": "2026-10-21T11:47:39.000000Z"
  }
}

===== 2. Client creates project =====
### POST /projects   (X-User-Id: 1)
{
  "title": "Marketplace MVP",
  "description": "Build the escrow API",
  "freelancer_ids": [
    2
  ]
}
--> HTTP 201
{
  "data": {
    "id": 1,
    "title": "Marketplace MVP",
    "description": "Build the escrow API",
    "status": "Open",
    "client": {
      "id": 1,
      "name": "Ada Client",
      "email": "ada+1792583258@example.com",
      "role": "client"
    },
    "freelancers": [
      {
        "id": 2,
        "name": "Linus Dev",
        "email": "linus+1792583258@example.com",
        "role": "freelancer"
      }
    ],
    "milestones": [],
    "totals": {
      "milestone_count": 0,
      "total_amount": 0,
      "funded_in_escrow": 0,
      "released": 0
    },
    "created_at": "2026-10-21T11:47:39.000000Z"
  }
}

===== 3. Client adds milestones (bulk) =====
### POST /projects/1/milestones   (X-User-Id: 1)
{
  "milestones": [
    {
      "title": "API design",
      "description": "Routes + schema",
      "amount": 500.00,
      "due_date": "2030-01-15"
    },
    {
      "title": "Implementation",
      "amount": 1500.50,
      "due_date": "2030-02-15"
    }
  ]
}
--> HTTP 201
{
  "data": [
    {
      "id": 1,
      "project_id": 1,
      "title": "API design",
      "description": "Routes + schema",
      "amount": 500,
      "currency": "USD",
      "due_date": "2030-01-15",
      "status": "Created",
      "funded_at": null,
      "released_at": null,
      "freelancer_id": 2,
      "created_at": "2026-10-21T11:47:40.000000Z"
    },
    {
      "id": 2,
      "project_id": 1,
      "title": "Implementation",
      "description": null,
      "amount": 1500.5,
      "currency": "USD",
      "due_date": "2030-02-15",
      "status": "Created",
      "funded_at": null,
      "released_at": null,
      "freelancer_id": 2,
      "created_at": "2026-10-21T11:47:40.000000Z"
    }
  ]
}

===== 4. Fund milestone BEFORE wallet funded (expect 422 insufficient_funds) =====
### POST /projects/1/milestones/1/fund   (X-User-Id: 1)
--> HTTP 422
{
  "error": {
    "code": "insufficient_funds",
    "message": "Wallet balance is insufficient to fund this milestone.",
    "details": {
      "available": 0,
      "required": 500
    }
  }
}

===== 5. Client funds wallet =====
### POST /wallets/1/fund   (X-User-Id: 1)
{
  "amount": 1000,
  "reference": "demo-topup-1"
}
--> HTTP 201
{
  "data": {
    "transaction": {
      "id": 1,
      "type": "wallet_funded",
      "direction": "credit",
      "amount": 1000,
      "available_balance_after": 1000,
      "escrow_balance_after": 0,
      "project_id": null,
      "milestone_id": null,
      "reference": "demo-topup-1",
      "description": "Simulated deposit into escrow wallet",
      "timestamp": "2026-10-21T11:47:41.000000Z"
    },
    "user": {
      "id": 1,
      "name": "Ada Client",
      "email": "ada+1792583258@example.com",
      "role": "client",
      "wallet": {
        "currency": "USD",
        "available_balance": 1000,
        "escrow_balance": 0
      },
      "created_at": "2026-10-21T11:47:38.000000Z"
    }
  }
}

===== 6. Release BEFORE funding milestone (expect 409) =====
### POST /projects/1/milestones/1/release   (X-User-Id: 1)
--> HTTP 409
{
  "error": {
    "code": "invalid_status_transition",
    "message": "Milestone cannot move from 'Created' to 'Released'.",
    "details": {
      "current_status": "Created"
    }
  }
}

===== 7. Fund milestone 1 (Created -> Funded, webhook fires) =====
### POST /projects/1/milestones/1/fund   (X-User-Id: 1)
--> HTTP 200
{
  "data": {
    "id": 1,
    "project_id": 1,
    "title": "API design",
    "description": "Routes + schema",
    "amount": 500,
    "currency": "USD",
    "due_date": "2030-01-15",
    "status": "Funded",
    "funded_at": "2026-10-21T11:47:41.000000Z",
    "released_at": null,
    "freelancer_id": 2,
    "involved_users": {
      "client": {
        "id": 1,
        "name": "Ada Client",
        "email": "ada+1792583258@example.com",
        "role": "client"
      },
      "freelancer": {
        "id": 2,
        "name": "Linus Dev",
        "email": "linus+1792583258@example.com",
        "role": "freelancer"
      }
    },
    "created_at": "2026-10-21T11:47:40.000000Z"
  }
}

===== 8. Freelancer tries to release (expect 403) =====
### POST /projects/1/milestones/1/release   (X-User-Id: 2)
--> HTTP 403
{
  "error": {
    "code": "forbidden",
    "message": "Only the project owner can perform this action."
  }
}

===== 9. Client releases milestone 1 (Funded -> Released, webhook fires) =====
### POST /projects/1/milestones/1/release   (X-User-Id: 1)
--> HTTP 200
{
  "data": {
    "id": 1,
    "project_id": 1,
    "title": "API design",
    "description": "Routes + schema",
    "amount": 500,
    "currency": "USD",
    "due_date": "2030-01-15",
    "status": "Released",
    "funded_at": "2026-10-21T11:47:41.000000Z",
    "released_at": "2026-10-21T11:47:42.000000Z",
    "freelancer_id": 2,
    "involved_users": {
      "client": {
        "id": 1,
        "name": "Ada Client",
        "email": "ada+1792583258@example.com",
        "role": "client"
      },
      "freelancer": {
        "id": 2,
        "name": "Linus Dev",
        "email": "linus+1792583258@example.com",
        "role": "freelancer"
      }
    },
    "created_at": "2026-10-21T11:47:40.000000Z"
  }
}

===== 10. Double release (expect 409) =====
### POST /projects/1/milestones/1/release   (X-User-Id: 1)
--> HTTP 409
{
  "error": {
    "code": "invalid_status_transition",
    "message": "Milestone cannot move from 'Released' to 'Released'.",
    "details": {
      "current_status": "Released"
    }
  }
}

===== 11. Milestone 2 exceeds remaining balance (expect 422) =====
### POST /projects/1/milestones/2/fund   (X-User-Id: 1)
--> HTTP 422
{
  "error": {
    "code": "insufficient_funds",
    "message": "Wallet balance is insufficient to fund this milestone.",
    "details": {
      "available": 500,
      "required": 1500.5
    }
  }
}

===== 12. Read endpoints =====
### GET /projects/1
--> HTTP 200
{
  "data": {
    "id": 1,
    "title": "Marketplace MVP",
    "description": "Build the escrow API",
    "status": "Open",
    "client": {
      "id": 1,
      "name": "Ada Client",
      "email": "ada+1792583258@example.com",
      "role": "client"
    },
    "freelancers": [
      {
        "id": 2,
        "name": "Linus Dev",
        "email": "linus+1792583258@example.com",
        "role": "freelancer"
      }
    ],
    "milestones": [
      {
        "id": 1,
        "project_id": 1,
        "title": "API design",
        "description": "Routes + schema",
        "amount": 500,
        "currency": "USD",
        "due_date": "2030-01-15",
        "status": "Released",
        "funded_at": "2026-10-21T11:47:41.000000Z",
        "released_at": "2026-10-21T11:47:42.000000Z",
        "freelancer_id": 2,
        "created_at": "2026-10-21T11:47:40.000000Z"
      },
      {
        "id": 2,
        "project_id": 1,
        "title": "Implementation",
        "description": null,
        "amount": 1500.5,
        "currency": "USD",
        "due_date": "2030-02-15",
        "status": "Created",
        "funded_at": null,
        "released_at": null,
        "freelancer_id": 2,
        "created_at": "2026-10-21T11:47:40.000000Z"
      }
    ],
    "totals": {
      "milestone_count": 2,
      "total_amount": 2000.5,
      "funded_in_escrow": 0,
      "released": 500
    },
    "created_at": "2026-10-21T11:47:39.000000Z"
  }
}

### GET /projects/1/milestones/1
--> HTTP 200
{
  "data": {
    "id": 1,
    "project_id": 1,
    "title": "API design",
    "description": "Routes + schema",
    "amount": 500,
    "currency": "USD",
    "due_date": "2030-01-15",
    "status": "Released",
    "funded_at": "2026-10-21T11:47:41.000000Z",
    "released_at": "2026-10-21T11:47:42.000000Z",
    "freelancer_id": 2,
    "involved_users": {
      "client": {
        "id": 1,
        "name": "Ada Client",
        "email": "ada+1792583258@example.com",
        "role": "client"
      },
      "freelancer": {
        "id": 2,
        "name": "Linus Dev",
        "email": "linus+1792583258@example.com",
        "role": "freelancer"
      }
    },
    "created_at": "2026-10-21T11:47:40.000000Z"
  }
}

### GET /users/1
--> HTTP 200
{
  "data": {
    "id": 1,
    "name": "Ada Client",
    "email": "ada+1792583258@example.com",
    "role": "client",
    "kyc": {
      "legal_full_name": "Ada Lovelace",
      "country": "GB",
      "government_id_type": "passport",
      "government_id_number": "*****5678",
      "date_of_birth": "1990-12-10",
      "status": "Verified",
      "verified_at": "2026-10-21T11:47:38.000000Z"
    },
    "wallet": {
      "currency": "USD",
      "available_balance": 500,
      "escrow_balance": 0
    },
    "created_at": "2026-10-21T11:47:38.000000Z"
  }
}

### GET /users/2
--> HTTP 200
{
  "data": {
    "id": 2,
    "name": "Linus Dev",
    "email": "linus+1792583258@example.com",
    "role": "freelancer",
    "kyc": {
      "legal_full_name": "Linus Torvalds",
      "country": "FI",
      "government_id_type": "national_id",
      "government_id_number": "*****8877",
      "date_of_birth": null,
      "status": "Verified",
      "verified_at": "2026-10-21T11:47:39.000000Z"
    },
    "wallet": {
      "currency": "USD",
      "available_balance": 500,
      "escrow_balance": 0
    },
    "created_at": "2026-10-21T11:47:39.000000Z"
  }
}

### GET /wallets/1/history
--> HTTP 200
{
  "data": {
    "user_id": 1,
    "currency": "USD",
    "available_balance": 500,
    "escrow_balance": 0,
    "entries": [
      {
        "id": 1,
        "type": "wallet_funded",
        "direction": "credit",
        "amount": 1000,
        "available_balance_after": 1000,
        "escrow_balance_after": 0,
        "project_id": null,
        "milestone_id": null,
        "reference": "demo-topup-1",
        "description": "Simulated deposit into escrow wallet",
        "timestamp": "2026-10-21T11:47:41.000000Z"
      },
      {
        "id": 2,
        "type": "milestone_funded",
        "direction": "hold",
        "amount": 500,
        "available_balance_after": 500,
        "escrow_balance_after": 500,
        "project_id": 1,
        "milestone_id": 1,
        "reference": null,
        "description": "Funds moved into escrow for milestone \"API design\"",
        "timestamp": "2026-10-21T11:47:41.000000Z"
      },
      {
        "id": 3,
        "type": "milestone_released",
        "direction": "debit",
        "amount": 500,
        "available_balance_after": 500,
        "escrow_balance_after": 0,
        "project_id": 1,
        "milestone_id": 1,
        "reference": null,
        "description": "Escrow released to freelancer #2 for \"API design\"",
        "timestamp": "2026-10-21T11:47:42.000000Z"
      }
    ]
  }
}

### GET /wallets/2/history
--> HTTP 200
{
  "data": {
    "user_id": 2,
    "currency": "USD",
    "available_balance": 500,
    "escrow_balance": 0,
    "entries": [
      {
        "id": 4,
        "type": "payment_received",
        "direction": "credit",
        "amount": 500,
        "available_balance_after": 500,
        "escrow_balance_after": 0,
        "project_id": 1,
        "milestone_id": 1,
        "reference": null,
        "description": "Payment received for milestone \"API design\"",
        "timestamp": "2026-10-21T11:47:42.000000Z"
      }
    ]
  }
}

### GET /users/999999
--> HTTP 404
{
  "error": {
    "code": "not_found",
    "message": "User not found."
  }
}

===== 13. Webhook deliveries =====
### GET /webhooks/deliveries
--> HTTP 200
{
  "data": [
    {
      "id": 2,
      "event": "milestone.released",
      "url": "http://127.0.0.1:8000/mock/webhook",
      "payload": {
        "id": "b4f6be27-a1a9-45df-b804-3bc38418cc08",
        "event": "milestone.released",
        "occurred_at": "2026-10-21T11:47:42+00:00",
        "data": {
          "project_id": 1,
          "milestone_id": 1,
          "status": "Released",
          "amount": 500,
          "currency": "USD",
          "client": {
            "user_id": 1,
            "name": "Ada Client",
            "email": "ada+1792583258@example.com",
            "role": "client",
            "kyc": {
              "legal_full_name": "Ada Lovelace",
              "country": "GB",
              "government_id_type": "passport",
              "government_id_number": "*****5678",
              "status": "Verified"
            }
          },
          "freelancer": {
            "user_id": 2,
            "name": "Linus Dev",
            "email": "linus+1792583258@example.com",
            "role": "freelancer",
            "kyc": {
              "legal_full_name": "Linus Torvalds",
              "country": "FI",
              "government_id_type": "national_id",
              "government_id_number": "*****8877",
              "status": "Verified"
            }
          }
        }
      },
      "response_status": 200,
      "success": true,
      "error": null,
      "created_at": "2026-10-21T11:47:42.000000Z"
    },
    {
      "id": 1,
      "event": "milestone.funded",
      "url": "http://127.0.0.1:8000/mock/webhook",
      "payload": {
        "id": "75f2503b-4cea-4a3d-9bba-44541426f33a",
        "event": "milestone.funded",
        "occurred_at": "2026-10-21T11:47:41+00:00",
        "data": {
          "project_id": 1,
          "milestone_id": 1,
          "status": "Funded",
          "amount": 500,
          "currency": "USD",
          "client": {
            "user_id": 1,
            "name": "Ada Client",
            "email": "ada+1792583258@example.com",
            "role": "client",
            "kyc": {
              "legal_full_name": "Ada Lovelace",
              "country": "GB",
              "government_id_type": "passport",
              "government_id_number": "*****5678",
              "status": "Verified"
            }
          },
          "freelancer": {
            "user_id": 2,
            "name": "Linus Dev",
            "email": "linus+1792583258@example.com",
            "role": "freelancer",
            "kyc": {
              "legal_full_name": "Linus Torvalds",
              "country": "FI",
              "government_id_type": "national_id",
              "government_id_number": "*****8877",
              "status": "Verified"
            }
          }
        }
      },
      "response_status": 200,
      "success": true,
      "error": null,
      "created_at": "2026-10-21T11:47:42.000000Z"
    }
  ]
}

```
