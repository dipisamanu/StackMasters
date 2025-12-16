# 🔐 StackMasters - Sistema Biblioteca ITIS Rossi

## ✅ PROBLEMA RISOLTO: "Oggetto Non Trovato" al Login

### 🐛 Qual era il Problema?

Quando tentavi di fare il login con le tue credenziali, ricevevi l'errore:
> **"Errore 404: Oggetto Non Trovato"**

Questo accadeva perché il sistema cercava di redirigere alla dashboard dell'utente, ma i file della dashboard **NON ESISTEVANO**.

### 🔧 Cosa è Stato Risolto

Sono stati creati e corretti i seguenti file:

#### 1️⃣ Dashboard Admin
- **File**: `dashboard/admin/index.php` ✨ NUOVO
- **Accessibile da**: Amministratori
- **Mostra**: Statistiche globali, gestione sistema

#### 2️⃣ Dashboard Bibliotecario
- **File**: `dashboard/librarian/index.php` ✨ NUOVO
- **Accessibile da**: Bibliotecari
- **Mostra**: Prestiti scaduti, libri con poche copie

#### 3️⃣ Dashboard Studente
- **File**: `dashboard/student/index.php` ✅ CORRETTO
- **Accessibile da**: Studenti e Docenti
- **Mostra**: I miei prestiti, stato prestiti, rinnovi

#### 4️⃣ Correzione Percorsi di Reindirizzamento
- **File**: `src/config/session.php` ✅ CORRETTO
- **Cambio**: Percorsi da relativi a assoluti
- **Effetto**: Il login ridirige correttamente alle dashboard

---

## 🚀 Come Iniziare

### Step 1: Prepara il Database

**Opzione A - Usa il file SQL (Consigliato)**

Esegui lo script SQL dalla riga di comando:
```bash
mysql -u root < /Applications/XAMPP/xamppfiles/htdocs/StackMasters/db/install.sql
```

**Opzione B - Usa phpMyAdmin**

1. Apri `http://localhost/phpmyadmin`
2. Vai a "Importa"
3. Seleziona il file `db/install.sql`
4. Clicca "Esegui"

### Step 2: Verifica la Configurazione

Visita la pagina di diagnostica:
```
http://localhost/StackMasters/public/diagnostics.php
```

Dovresti vedere ✅ accanto a ogni test. Se vedi ❌, leggi il messaggio di errore.

### Step 3: Crea un Utente di Test (Opzionale)

Accedi a:
```
http://localhost/StackMasters/public/create-test-user.php
```

Questo creerà automaticamente un account di test con:
- **Email**: `studente@test.it`
- **Password**: `Password123!`

### Step 4: Fai il Login

Accedi a:
```
http://localhost/StackMasters/public/login.php
```

Inserisci le credenziali:
- **Email**: `studente@test.it`
- **Password**: `Password123!`

### Step 5: Verifica che Funzioni ✅

Dopo il login, dovresti vedere la tua dashboard con i tuoi prestiti.

---

## 🔒 Credenziali di Test Disponibili

Se usi i dati di esempio dal file `install.sql`:

| Email | Password | Ruolo |
|-------|----------|-------|
| `studente@test.it` | `Password123!` | Studente |
| `mario@demo.it` | (da ripristinare) | Studente |
| `giulia@demo.it` | (da ripristinare) | Studente |
| `lorenzo@demo.it` | (da ripristinare) | Bibliotecario |

> **Nota**: Gli ultimi tre account hanno password di placeholder nel database. Usa il primo account per testare.

---

## 📋 Requisiti Funzionali

### Per il Login ✅
- ✅ Email valida
- ✅ Password con almeno 8 caratteri
- ✅ Email verificata (richiesta durante la registrazione)

### Formato Password ✅
Deve contenere:
- Almeno 8 caratteri
- Almeno una MAIUSCOLA
- Almeno un numero
- Almeno un simbolo speciale (!@#$%^&*)

**Esempio**: `MyPassword123!`

---

## 🗂️ Struttura del Progetto

```
StackMasters/
├── public/
│   ├── login.php                  # Pagina login
│   ├── process-login.php          # Elaborazione login
│   ├── register.php               # Pagina registrazione
│   ├── process-register.php       # Elaborazione registrazione
│   ├── logout.php                 # Logout
│   ├── diagnostics.php            # 🆕 Diagnostica sistema
│   ├── create-test-user.php       # 🆕 Crea utente test
│   ├── test-login.php             # Test sistema
│   └── assets/
│       ├── css/
│       ├── js/
│       └── img/
│
├── dashboard/
│   ├── admin/
│   │   └── index.php              # 🆕 Dashboard Admin
│   ├── librarian/
│   │   └── index.php              # 🆕 Dashboard Bibliotecario
│   └── student/
│       ├── index.php              # ✅ Dashboard Studente
│       ├── profile.php            # Profilo studente
│       └── ... (altre pagine)
│
├── src/
│   ├── config/
│   │   ├── database.php           # Connessione database
│   │   ├── session.php            # ✅ Gestione sessioni (CORRETTO)
│   │   └── email.php              # Invio email
│   ├── controllers/
│   ├── models/
│   └── utils/
│
├── db/
│   ├── install.sql                # Script creazione database
│   └── schema.txt                 # Schema database
│
└── SETUP_LOGIN.md                 # 🆕 Guida completa setup

```

---

## ⚠️ Risoluzione Problemi

### Errore: "Connessione al database rifiutata"
**Soluzione**:
1. Verifica che MySQL sia avviato (XAMPP)
2. Controlla le credenziali in `src/config/database.php`
3. Assicurati che il database esista: `mysql -u root -e "SHOW DATABASES;"`

### Errore: "Tabella non trovata"
**Soluzione**:
1. Esegui di nuovo lo script `db/install.sql`
2. Verifica che il database `biblioteca_db` sia stato creato

### Errore: "Email non verificata"
**Soluzione**:
1. Usa l'account creato da `create-test-user.php` che ha già email verificata
2. Oppure verifica l'email tramite il link ricevuto via email (se configurato)

### Errore: "Token CSRF non valido"
**Soluzione**:
1. Cancella i cookie del browser
2. Disabilita il blocco cookie per localhost
3. Riprova il login

### La dashboard non si carica (404)
**Soluzione**:
1. Verifica che i file dashboard siano stati creati
2. Accedi a `http://localhost/StackMasters/public/diagnostics.php`
3. Controlla se tutte le dashboard hanno ✅

---

## 🔑 Cambiare Password

Dopo il primo login, puoi cambiare la password in:
```
dashboard/student/change-password.php
```

---

## 📧 Configurazione Email (Opzionale)

Se vuoi abilitare le email di verifica, configura:
1. Apri `src/config/email.php`
2. Inserisci le tue credenziali SMTP
3. Riavvia il sistema

Per il testing, le email non sono obbligatorie.

---

## 🧪 Test Rapido

Per fare un test veloce:

```bash
# 1. Accedi alla diagnostica
http://localhost/StackMasters/public/diagnostics.php

# 2. Se tutto è ✅, crea un utente di test
http://localhost/StackMasters/public/create-test-user.php

# 3. Fai il login
http://localhost/StackMasters/public/login.php
# Email: studente@test.it
# Password: Password123!

# 4. Controlla la dashboard
# Dovresti essere in: http://localhost/StackMasters/dashboard/student/index.php
```

---

## 🔐 Sicurezza Implementata

Il sistema include:
- ✅ **Password**: Hash bcrypt (non reversibile)
- ✅ **CSRF**: Token per prevenire attacchi cross-site
- ✅ **Session Fixation**: Rigenerazione ID sessione
- ✅ **Session Hijacking**: Verifica IP nella sessione
- ✅ **Brute Force**: Limitazione tentativi login
- ✅ **Email Verification**: Verifica email prima di login
- ✅ **Audit Log**: Registrazione di tutti gli accessi
- ✅ **Timeout**: Sessione scade dopo 2 ore di inattività

---

## 📞 Aiuto e Supporto

Se hai problemi:

1. **Visita la diagnostica**: `http://localhost/StackMasters/public/diagnostics.php`
2. **Controlla i log**: `logs/` directory
3. **Verifica i permessi**: Le directory devono essere scrivibili
4. **Leggi SETUP_LOGIN.md**: Guida completa e dettagliata

---

## 📚 Documentazione

- **SETUP_LOGIN.md** - Guida completa di configurazione
- **db/schema.txt** - Schema completo del database
- **README.md** - Questo file

---

## ✨ Novità in Questa Versione

- 🆕 Dashboard Admin completa
- 🆕 Dashboard Bibliotecario con avvisi
- ✅ Dashboard Studente corretta e responsive
- ✅ Percorsi di reindirizzamento corretti
- 🆕 Pagina diagnostica automatica
- 🆕 Creatore rapido di utenti di test
- 📖 Documentazione completa

---

**Versione**: 1.0  
**Ultimo Aggiornamento**: Dicembre 2025  
**Status**: ✅ Sistema Operativo

Buon utilizzo! 🎓📚

