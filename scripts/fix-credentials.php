<?php
$json = [
  "type" => "service_account",
  "project_id" => "edcada-tester",
  "private_key_id" => "2350ab75dc64bcb481c4725a8360b6b76347a4eb",
  "private_key" => "-----BEGIN PRIVATE KEY-----\nMIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQDTPZNRsVEEVLUJ\neGDs88aAtwuOGzextg0Th5bVCF24nILkWzKDAzeKwurUyI6wLYNy0hmOFf0mhivl\nTCap+r1tmFcAjDZNb1kf/oC5F095xdEGDE99cANtKrnfQYA1E9YdKgKULDDjCsRv\nlsvSahr/nQ9M1/A3QNxnomV5U3SIUpYKDznyDi1f5JqFoFAYelUmlAAoONPV44pd\n6hKTNc3hJEZ7uhzcrRO3Jn4NlZonnhKGjiW3i9lQWfydLaardGgQvgffAfhp6IFw\nwMuGJBp/GM6ZArH86C6X09jDxVwt5yjMQ9cx6tKGt2+Uu565Daz9KY2H15R+vsnP\nBMg8sB19AgMBAAECggEABHC0/fYHINsX+BmTQ1Und8ar4+zktDlXrTCqhhKth6cl\n5YoY4HdK7awwYVB67ASfkKRjxvW63cUFu1rpAtCDYVWfBI2t9DLnkcbY1VXXr5St\n0rL5C8cKPLtLGhXczaDRCB69MaNRKWtCmvbgHHNLBdL7F+9/vDrtNxzLMGxFlOT1\nDuUpZhLD/n8XChbSHmae0HIlD0bJKyZZywZBqeNp/2BYv9x3ExqYpKIlnFS44DpM\nTUa5buf8vBuKTINQvVZZ+uQV0Hy2S8D8QoNPOznNbXizNw4gscyFUccoLT4SroJf\nB1JWq73oZ2wZdT7+Xc4XG+nUU0gAAbS2Rii0T0ueSQKBgQD1/69zK37eSO89+i6H\nSWLDXNE46wO9Wt+i/eDdWRP68CPteSZCoyhawmoLMllZZ81AUZwHYjigFTGzeK+D\nxbIuANijwAVNi/lsubdJ76rIloWwjpWAM9PaxhFE8U3fktqs2YQhXrYpk0ECfZNC\nt4zYeQfntVtLSolGaprDiJFyaQKBgQDbCHPAGKaiRctJ7GcOtiYHA+2Q/j3Ne2p\nNNIWzRCb9VnVbtDw/eltylvc/IfNS2fZ47tl9ojDfQu/LtDwlf0TqHfR70j2CuZM\nO5myrAop22s0wf8aqbYKs12GGs1bBEBPGINY1PDlmFOW2qWqyjXouYnu1XnNF2Uy\n06eM317H9QKBgE5XUgGkfW++3Gnpbb3p0gkTWxH8TiGUehoHLgBv6NwGc/qhlVyt\nZyGYPns4WpoNY6EzHDSBxDS+6ygTrBmT8Q2TeWqUsVuj0xgcANIMAGCHByZWEihU\n2QgYAdHp4vnrY7aeQuT5q5uL6K0pXqdlmvYpfSn+aIeOwi7prkXNDTzZAoGARR1c\nB9YqKkYh6EuLlwAVazWfZwHV+/uTnliCGTMeHrq0JNuzi6F5S9CMs10eYVhs7V+h\nYrxxYW0mTVSt0oaFzDFygqnF+b2RjLRMbZWTmHdpLGw2Ba8IEjM0m14/5HbgtT2S\nxlIk7zrGRS63WYw8CNCU4mdx5R6O7b0H982e4iUCgYBebRhVHdgZdEo6kSvF6BAT\nGNyal0/9YPeFHmV1cHlWJWFnmXZ5zUbvudlUxUlOwZ0rDFQoHO57qUgb7c01vPUt\nedoEtRkueh1SOEchZcVOGHcYKDsxX0bCBZ5s0o3b8mpkdeJuM13zP2LPRaBn6iz8\nd+2sb3IngpZ+VVqtrDQ1AQ==\n-----END PRIVATE KEY-----",
  "client_email" => "edcadaapp-reviewer-tester@edcada-tester.iam.gserviceaccount.com",
  "client_id" => "115531821945652034858",
  "auth_uri" => "https://accounts.google.com/o/oauth2/auth",
  "token_uri" => "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url" => "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url" => "https://www.googleapis.com/robot/v1/metadata/x509/edcadaapp-reviewer-tester%40edcada-tester.iam.gserviceaccount.com",
  "universe_domain" => "googleapis.com"
];
file_put_contents('storage/app/private/edcada-credentials.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
