# ==========================================
# IMPORTS
# ==========================================

from flask import Flask, jsonify, request
from flask_cors import CORS
from flask_sqlalchemy import SQLAlchemy
from flask_jwt_extended import (
    JWTManager,
    create_access_token,
    jwt_required,
    get_jwt_identity
)

from sqlalchemy import text
from datetime import timedelta, datetime
from functools import wraps
from urllib.parse import quote_plus
import os
import re
from dotenv import load_dotenv


# ==========================================
# LOGIN SECURITY SETTINGS
# ==========================================

MAX_ATTEMPTS = 3
LOCK_MINUTES = 1

# ==========================================
# LOAD ENVIRONMENT VARIABLES
# ==========================================

load_dotenv()


# ==========================================
# CREATE FLASK APPLICATION
# ==========================================

app = Flask(__name__)

CORS(app)


# ==========================================
# DATABASE CONFIGURATION
# ==========================================

db_user = os.getenv("DB_USER")
db_password_raw = os.getenv("DB_PASSWORD", "")
db_password = quote_plus(db_password_raw)
db_host = os.getenv("DB_HOST")
db_port = os.getenv("DB_PORT", "3306")
db_name = os.getenv("DB_NAME")

app.config["SQLALCHEMY_DATABASE_URI"] = (
    f"mysql+pymysql://{db_user}:{db_password}"
    f"@{db_host}:{db_port}/{db_name}"
)

app.config["SQLALCHEMY_TRACK_MODIFICATIONS"] = False


# ==========================================
# SECURITY CONFIGURATION
# ==========================================

app.config["SECRET_KEY"] = os.getenv(
    "SECRET_KEY",
    "SeniorConnect-Secret-Key-2026"
)

app.config["JWT_SECRET_KEY"] = os.getenv(
    "JWT_SECRET_KEY",
    "SeniorConnect-JWT-Secret-Key-2026-Secure"
)

app.config["JWT_ACCESS_TOKEN_EXPIRES"] = timedelta(hours=2)


# ==========================================
# INITIALIZE EXTENSIONS
# ==========================================

db = SQLAlchemy(app)

jwt = JWTManager(app)


# ==========================================
# USER MODEL
# ==========================================

class User(db.Model):

    __tablename__ = "users"

    user_id = db.Column(
        db.Integer,
        primary_key=True
    )

    name = db.Column(
        db.String(255),
        nullable=False
    )

    phone = db.Column(
        db.String(20),
        unique=True,
        nullable=False
    )

    role = db.Column(
        db.Enum(
            "admin",
            "staff",
            "attendee"
        ),
        default="attendee"
    )

    # Plain 4-digit PIN
    pin_code = db.Column(
        db.String(4),
        nullable=True
    )

    is_online = db.Column(
        db.Boolean,
        default=False
    )

    last_login_at = db.Column(
        db.DateTime,
        nullable=True
    )

    # Login attempt protection
    failed_attempts = db.Column(
        db.Integer,
        nullable=False,
        default=0
    )

    locked_until = db.Column(
        db.DateTime,
        nullable=True
    )


# ==========================================
# CATEGORY MODEL
# ==========================================

class Category(db.Model):

    __tablename__ = "categories"

    category_id = db.Column(
        db.Integer,
        primary_key=True
    )

    name = db.Column(
        db.String(255),
        nullable=False
    )

    description = db.Column(
        db.Text
    )


# ==========================================
# LOCATION MODEL
# ==========================================

class Location(db.Model):

    __tablename__ = "locations"

    location_id = db.Column(
        db.Integer,
        primary_key=True
    )

    address = db.Column(
        db.String(255)
    )

    zip = db.Column(
        db.String(20)
    )

    map_link = db.Column(
        db.String(255)
    )


# ==========================================
# ANNOUNCEMENT MODEL
# ==========================================

class Announcement(db.Model):

    __tablename__ = "announcements"

    announcement_id = db.Column(
        db.BigInteger,
        primary_key=True
    )

    posted_by = db.Column(
        db.BigInteger,
        db.ForeignKey("users.user_id"),
        nullable=True
    )

    title = db.Column(
        db.String(255),
        nullable=False
    )

    content = db.Column(
        db.Text
    )

    time_created = db.Column(
        db.DateTime,
        server_default=db.func.now()
    )

    schedule = db.Column(
        db.DateTime,
        nullable=True
    )

    user = db.relationship(
        "User",
        backref="announcements"
    )


# ==========================================
# RESOURCE MODEL
# ==========================================

class Resource(db.Model):

    __tablename__ = "resources"

    resource_id = db.Column(
        db.Integer,
        primary_key=True
    )

    name = db.Column(
        db.String(255),
        nullable=False
    )

    type = db.Column(
        db.String(50)
    )

    status = db.Column(
        db.String(50)
    )


# ==========================================
# EVENT MODEL
# ==========================================

class Event(db.Model):

    __tablename__ = "events"

    event_id = db.Column(
        db.Integer,
        primary_key=True
    )

    created_by = db.Column(
        db.Integer,
        db.ForeignKey("users.user_id"),
        nullable=True
    )

    category_id = db.Column(
        db.Integer,
        db.ForeignKey("categories.category_id"),
        nullable=True
    )

    location_id = db.Column(
        db.Integer,
        db.ForeignKey("locations.location_id"),
        nullable=True
    )

    title = db.Column(
        db.String(255),
        nullable=False
    )

    start_time = db.Column(
        db.DateTime
    )

    end_time = db.Column(
        db.DateTime
    )

    capacity = db.Column(
        db.Integer
    )

    creator = db.relationship(
        "User",
        backref="events_created"
    )

    category = db.relationship(
        "Category",
        backref="events"
    )

    location = db.relationship(
        "Location",
        backref="events"
    )


# ==========================================
# REGISTRATION MODEL
# ==========================================

class Registration(db.Model):

    __tablename__ = "registrations"

    registration_id = db.Column(
        db.Integer,
        primary_key=True
    )

    user_id = db.Column(
        db.Integer,
        db.ForeignKey("users.user_id"),
        nullable=False
    )

    event_id = db.Column(
        db.Integer,
        db.ForeignKey("events.event_id"),
        nullable=False
    )

    registration_time = db.Column(
        db.DateTime,
        server_default=db.func.now()
    )

    status = db.Column(
        db.String(50),
        default="registered"
    )

    user = db.relationship(
        "User",
        backref="registrations"
    )

    event = db.relationship(
        "Event",
        backref=db.backref(
            "registrations",
            cascade="all, delete-orphan",
            passive_deletes=True
        )
    )


# ==========================================
# EVENT RESOURCE MODEL
# ==========================================

class EventResource(db.Model):

    __tablename__ = "event_resources"

    event_resource_id = db.Column(
        db.Integer,
        primary_key=True
    )

    event_id = db.Column(
        db.Integer,
        db.ForeignKey("events.event_id"),
        nullable=False
    )

    resource_id = db.Column(
        db.Integer,
        db.ForeignKey("resources.resource_id"),
        nullable=False
    )

    quantity = db.Column(
        db.Integer
    )

    event = db.relationship(
        "Event",
        backref=db.backref(
            "event_resources",
            cascade="all, delete-orphan",
            passive_deletes=True
        )
    )

    resource = db.relationship(
        "Resource",
        backref=db.backref(
            "event_resources",
            cascade="all, delete-orphan",
            passive_deletes=True
        )
    )


# ==========================================
# AUDIT LOG MODEL
# ==========================================

class AuditLog(db.Model):

    __tablename__ = "audit_logs"

    log_id = db.Column(
        db.BigInteger,
        primary_key=True
    )

    user_id = db.Column(
        db.BigInteger,
        db.ForeignKey("users.user_id"),
        nullable=True
    )

    details = db.Column(
        db.Text
    )

    timestamp = db.Column(
        db.DateTime,
        server_default=db.func.now()
    )


# ==========================================
# CREATE AUDIT LOG
# ==========================================

def create_audit_log(user_id, details):

    audit_log = AuditLog(
        user_id=user_id,
        details=details
    )

    db.session.add(audit_log)


# ==========================================
# TEST ROUTE - BACKEND
# ==========================================

@app.route("/")
def home():

    return jsonify({
        "message": "Backend is running!"
    })


# ==========================================
# TEST ROUTE - DATABASE CONNECTION
# ==========================================

@app.route("/test-db")
def test_database():

    try:

        db.session.execute(text("SELECT 1"))

        return jsonify({
            "success": True,
            "message": "Database connection successful!"
        })

    except Exception as e:

        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# ==========================================
# TEST ROUTE - USERS TABLE
# ==========================================

@app.route("/test-users")
def test_users():

    try:

        users = User.query.all()

        results = []

        for user in users:

            results.append({
                "user_id": user.user_id,
                "name": user.name,
                "phone": user.phone,
                "role": user.role,
                "is_online": user.is_online
            })

        return jsonify({
            "success": True,
            "users": results
        })

    except Exception as e:

        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# ==========================================
# TEST ANNOUNCEMENTS
# ==========================================

@app.route("/test-announcements")
def test_announcements():

    try:

        announcements = Announcement.query.all()

        return jsonify({
            "success": True,
            "announcements": [
                {
                    "announcement_id": announcement.announcement_id,
                    "posted_by": announcement.posted_by,
                    "title": announcement.title,
                    "content": announcement.content,
                    "time_created": str(
                        announcement.time_created
                    ),
                    "schedule": str(
                        announcement.schedule
                    ) if announcement.schedule else None
                }
                for announcement in announcements
            ]
        })

    except Exception as e:

        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# ==========================================
# TEST CATEGORIES
# ==========================================

@app.route("/test-categories")
def test_categories():

    try:

        categories = Category.query.all()

        return jsonify({
            "success": True,
            "categories": [
                {
                    "category_id": category.category_id,
                    "name": category.name,
                    "description": category.description
                }
                for category in categories
            ]
        })

    except Exception as e:

        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# ==========================================
# TEST LOCATIONS
# ==========================================

@app.route("/test-locations")
def test_locations():

    try:

        locations = Location.query.all()

        return jsonify({
            "success": True,
            "locations": [
                {
                    "location_id": location.location_id,
                    "address": location.address,
                    "zip": location.zip,
                    "map_link": location.map_link
                }
                for location in locations
            ]
        })

    except Exception as e:

        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# ==========================================
# TEST RESOURCES
# ==========================================

@app.route("/test-resources")
def test_resources():

    try:

        resources = Resource.query.all()

        return jsonify({
            "success": True,
            "resources": [
                {
                    "resource_id": resource.resource_id,
                    "name": resource.name,
                    "type": resource.type,
                    "status": resource.status
                }
                for resource in resources
            ]
        })

    except Exception as e:

        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# ==========================================
# TEST EVENTS
# ==========================================

@app.route("/test-events")
def test_events():

    try:

        events = Event.query.all()

        return jsonify({
            "success": True,
            "events": [
                {
                    "event_id": event.event_id,
                    "created_by": event.created_by,
                    "category_id": event.category_id,
                    "location_id": event.location_id,
                    "title": event.title,
                    "start_time": str(event.start_time),
                    "end_time": str(event.end_time),
                    "capacity": event.capacity
                }
                for event in events
            ]
        })

    except Exception as e:

        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# ==========================================
# TEST AUDIT LOGS
# ==========================================

@app.route("/test-audit-logs")
def test_audit_logs():

    try:

        audit_logs = AuditLog.query.all()

        return jsonify({
            "success": True,
            "audit_logs": [
                {
                    "log_id": log.log_id,
                    "user_id": log.user_id,
                    "details": log.details,
                    "timestamp": str(log.timestamp)
                }
                for log in audit_logs
            ]
        })

    except Exception as e:

        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# ==========================================
# TEST EVENT RESOURCES
# ==========================================

@app.route("/test-event-resources")
def test_event_resources():

    try:

        event_resources = EventResource.query.all()

        return jsonify({
            "success": True,
            "event_resources": [
                {
                    "event_resource_id":
                        event_resource.event_resource_id,

                    "event_id":
                        event_resource.event_id,

                    "resource_id":
                        event_resource.resource_id,

                    "quantity":
                        event_resource.quantity
                }
                for event_resource in event_resources
            ]
        })

    except Exception as e:

        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


# ==========================================
# REGISTER
# ==========================================

@app.route("/api/auth/register", methods=["POST"])
def register():

    data = request.get_json() or {}

    name = str(data.get("name", "")).strip()
    phone = str(data.get("phone", "")).strip()
    code = str(data.get("code", data.get("pin_code", ""))).strip()

    if not name or not phone or not code:

        return jsonify({
            "message": "Name, phone number, and PIN are required"
        }), 400

    # PIN must be exactly 4 numeric digits
    if not code.isdigit() or len(code) != 4:

        return jsonify({
            "message": "PIN must be exactly 4 digits"
        }), 400

    existing_user = User.query.filter_by(
        phone=phone
    ).first()

    if existing_user:

        return jsonify({
            "message": "An account with this phone number already exists"
        }), 409

    # ==========================================
    # PLAIN PIN
    # ==========================================

    user = User(
        name=name,
        phone=phone,
        pin_code=code,
        role="attendee"
    )

    db.session.add(user)

    db.session.commit()

    return jsonify({
        "message": "User registered successfully"
    }), 201


# ==========================================
# LOGIN
# ==========================================

@app.route("/api/auth/login", methods=["POST"])
def login():

    data = request.get_json(silent=True) or {}

    name = (data.get("name") or "").strip()
    phone = (data.get("phone") or "").strip()
    code = (data.get("code") or "").strip()

    # ==============================
    # INPUT VALIDATION
    # ==============================

    if not name:
        return jsonify({
            "message": "Full name is required."
        }), 400

    if not re.fullmatch(r"[A-Za-zÀ-ÿ\s'-]+", name):
        return jsonify({
            "message": "Full name must contain letters only."
        }), 400

    if not phone or not code:
        return jsonify({
            "message": "Name, phone number, and PIN are required."
        }), 400

    if not code.isdigit() or len(code) != 4:
        return jsonify({
            "message": "PIN must be exactly 4 digits."
        }), 400

    # ==============================
    # DATABASE LOOKUP
    # ==============================
    # SQLAlchemy parameterizes the phone value.
    # User input is treated as data, not SQL code.

    user = User.query.filter_by(phone=phone).first()

    if not user:
        return jsonify({
            "message": "Invalid name, phone number, or PIN."
        }), 401

    # ==============================
    # VERIFY NAME
    # ==============================

    if user.name.strip().lower() != name.lower():
        return jsonify({
            "message": "Invalid name, phone number, or PIN."
        }), 401

    # ==============================
    # VERIFY PIN
    # ==============================

    if user.pin_code != code:
        return jsonify({
            "message": "Invalid name, phone number, or PIN."
        }), 401

    # ==============================
    # SUCCESSFUL LOGIN
    # ==============================

    user.is_online = True
    user.last_login_at = db.func.now()

    db.session.commit()

    # ==============================
    # CREATE JWT TOKEN
    # ==============================

    access_token = create_access_token(
        identity=str(user.user_id)
    )

    return jsonify({
        "message": "Login successful",
        "access_token": access_token,
        "user": {
            "user_id": user.user_id,
            "name": user.name,
            "phone": user.phone,
            "role": user.role
        }
    }), 200


# ==========================================
# LOGOUT
# ==========================================

@app.route("/api/auth/logout", methods=["POST"])
@jwt_required()
def logout():

    user_id = get_jwt_identity()

    user = db.session.get(
        User,
        int(user_id)
    )

    if user:

        user.is_online = False

        db.session.commit()

    return jsonify({
        "message": "Logged out successfully"
    })


# ==========================================
# GET ANNOUNCEMENTS
# ==========================================

@app.route("/api/announcements", methods=["GET"])
def get_announcements():

    try:

        announcements = Announcement.query.all()

        return jsonify({
            "success": True,
            "announcements": [
                {
                    "announcement_id": announcement.announcement_id,
                    "posted_by": announcement.posted_by,
                    "title": announcement.title,
                    "content": announcement.content,
                    "time_created": str(announcement.time_created),
                    "schedule": str(announcement.schedule) if announcement.schedule else None
                }
                for announcement in announcements
            ]
        }), 200

    except Exception as e:

        return jsonify({
            "success": False,
            "error": str(e)
        }), 500


if __name__ == "__main__":
    app.run(debug=False)

