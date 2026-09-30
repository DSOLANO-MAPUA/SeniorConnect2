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
db_name = os.getenv("DB_NAME")

app.config["SQLALCHEMY_DATABASE_URI"] = (
    f"mysql+pymysql://{db_user}:{db_password}"
    f"@{db_host}/{db_name}"
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
    # REQUIRED FIELDS
    # ==========================================

    if not name or not phone or not code:

        return jsonify({
            "message": "Name, phone number, and PIN are required"
        }), 400

    # ==========================================
    # PIN VALIDATION
    # ==========================================

    if not code.isdigit() or len(code) != 4:

        return jsonify({
            "message": "PIN must be exactly 4 digits"
        }), 400

    # ==========================================
    # FIND USER
    # ==========================================

    user = User.query.filter_by(
        phone=phone
    ).first()

    # ==========================================
    # CHECK ACCOUNT LOCK
    # ==========================================

    if user and user.locked_until:

        now = datetime.now()

        if user.locked_until > now:

            wait = int(
                (user.locked_until - now).total_seconds()
            )

            return jsonify({

                "message":
                    f"Too many attempts. "
                    f"Try again in {wait // 60 + 1} minute(s).",

                "retry_after":
                    wait

            }), 429

        else:

            # Lock has expired
            user.locked_until = None
            user.failed_attempts = 0

            db.session.commit()

    # ==========================================
    # CHECK NAME
    # ==========================================

    name_matches = (
        user is not None
        and user.name.strip().lower() == name.lower()
    )

    # ==========================================
    # INVALID LOGIN
    # ==========================================

    if (
        not user
        or not name_matches
        or not user.pin_code
        or user.pin_code != code
    ):

        # Only count attempts when the phone
        # number belongs to an existing account.
        if user:

            user.failed_attempts += 1

            # ==========================================
            # LOCK ACCOUNT AFTER MAX ATTEMPTS
            # ==========================================

            if user.failed_attempts >= MAX_ATTEMPTS:

                user.locked_until = (
                    datetime.now()
                    + timedelta(minutes=LOCK_MINUTES)
                )

                # Reset counter after locking
                user.failed_attempts = 0

                db.session.commit()

                return jsonify({

                    "message":
                        "Too many failed attempts. "
                        "Your account has been locked for 5 minutes.",

                    "retry_after":
                        LOCK_MINUTES * 60

                }), 429

            db.session.commit()

        return jsonify({
            "message": "Invalid name, phone number, or PIN"
        }), 401

    # ==========================================
    # SUCCESSFUL LOGIN
    # ==========================================

    user.failed_attempts = 0
    user.locked_until = None

    user.is_online = True

    user.last_login_at = db.func.now()

    db.session.commit()

    # ==========================================
    # CREATE JWT TOKEN
    # ==========================================

    access_token = create_access_token(
        identity=str(user.user_id)
    )

    # ==========================================
    # LOGIN RESPONSE
    # ==========================================

    return jsonify({

        "message": "Login successful",

        "access_token": access_token,

        "user": {

            "user_id":
                user.user_id,

            "name":
                user.name,

            "phone":
                user.phone,

            "role":
                user.role

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
    }), 200


# ==========================================
# ROLE AUTHORIZATION
# ==========================================

def role_required(*allowed_roles):

    def decorator(function):

        @wraps(function)

        @jwt_required()

        def wrapper(*args, **kwargs):

            user_id = get_jwt_identity()

            user = db.session.get(
                User,
                int(user_id)
            )

            if not user:

                return jsonify({
                    "message": "User not found"
                }), 404

            if user.role not in allowed_roles:

                return jsonify({
                    "message": "Access denied"
                }), 403

            return function(*args, **kwargs)

        return wrapper

    return decorator


# ==========================================
# CREATE EVENT
# ==========================================

@app.route("/api/events", methods=["POST"])
@role_required("admin", "staff")
def create_event():

    data = request.get_json(silent=True) or {}

    user_id = get_jwt_identity()

    event = Event(

        created_by=int(user_id),

        category_id=data.get("category_id"),

        location_id=data.get("location_id"),

        title=data.get("title"),

        start_time=data.get("start_time"),

        end_time=data.get("end_time"),

        capacity=data.get("capacity")

    )

    db.session.add(event)

    create_audit_log(
        int(user_id),
        f"Created event: {event.title}"
    )

    db.session.commit()

    return jsonify({

        "message": "Event created successfully",

        "event_id": event.event_id

    }), 201


# ==========================================
# GET ALL EVENTS
# ==========================================

@app.route("/api/events", methods=["GET"])
def get_events():

    search = request.args.get(
        "search",
        ""
    ).strip()

    query = Event.query

    if search:

        query = query.filter(
            Event.title.ilike(
                f"%{search}%"
            )
        )

    events = query.all()

    results = []

    for event in events:

        results.append({

            "event_id": event.event_id,

            "title": event.title,

            "start_time": str(event.start_time),

            "end_time": str(event.end_time),

            "capacity": event.capacity,

            "category": event.category.name
            if event.category else None,

            "location": event.location.address
            if event.location else None

        })

    return jsonify(results)


# ==========================================
# GET ONE EVENT
# ==========================================

@app.route(
    "/api/events/<int:event_id>",
    methods=["GET"]
)
def get_event(event_id):

    event = Event.query.get_or_404(
        event_id
    )

    return jsonify({

        "event_id": event.event_id,

        "title": event.title,

        "start_time": str(event.start_time),

        "end_time": str(event.end_time),

        "capacity": event.capacity

    })


# ==========================================
# UPDATE EVENT
# ==========================================

@app.route(
    "/api/events/<int:event_id>",
    methods=["PUT"]
)
@role_required("admin", "staff")
def update_event(event_id):

    event = Event.query.get_or_404(
        event_id
    )

    data = request.get_json(
        silent=True
    ) or {}

    event.title = data.get(
        "title",
        event.title
    )

    event.capacity = data.get(
        "capacity",
        event.capacity
    )

    event.category_id = data.get(
        "category_id",
        event.category_id
    )

    event.location_id = data.get(
        "location_id",
        event.location_id
    )

    create_audit_log(
        int(get_jwt_identity()),
        f"Updated event ID: {event_id}"
    )

    db.session.commit()

    return jsonify({
        "message": "Event updated successfully"
    })


# ==========================================
# DELETE EVENT
# ==========================================

@app.route(
    "/api/events/<int:event_id>",
    methods=["DELETE"]
)
@role_required("admin")
def delete_event(event_id):

    event = Event.query.get_or_404(
        event_id
    )

    db.session.delete(event)

    create_audit_log(
        int(get_jwt_identity()),
        f"Deleted event ID: {event_id}"
    )

    db.session.commit()

    return jsonify({
        "message": "Event deleted successfully"
    })


# ==========================================
# REGISTER FOR EVENT
# ==========================================

@app.route(
    "/api/events/<int:event_id>/register",
    methods=["POST"]
)
@role_required("attendee")
def register_for_event(event_id):

    user_id = int(
        get_jwt_identity()
    )

    event = Event.query.get_or_404(
        event_id
    )

    existing_registration = Registration.query.filter_by(

        user_id=user_id,

        event_id=event_id

    ).first()

    if existing_registration:

        return jsonify({
            "message": "Already registered"
        }), 409

    registration_count = Registration.query.filter_by(

        event_id=event_id,

        status="registered"

    ).count()

    if event.capacity and registration_count >= event.capacity:

        return jsonify({
            "message": "Event is already full"
        }), 400

    registration = Registration(

        user_id=user_id,

        event_id=event_id,

        status="registered"

    )

    db.session.add(registration)

    create_audit_log(
        user_id,
        f"Registered for event ID: {event_id}"
    )

    db.session.commit()

    return jsonify({
        "message": "Successfully registered for event"
    }), 201


# ==========================================
# GET USERS - ADMIN / STAFF
# ==========================================

@app.route("/api/users", methods=["GET"])
@role_required("admin", "staff")
def get_users():

    search = request.args.get("search", "").strip()

    query = User.query

    if search:
        like = f"%{search}%"

        query = query.filter(
            db.or_(
                User.name.ilike(like),
                User.phone.ilike(like)
            )
        )

    users = query.order_by(
        User.name.asc()
    ).all()

    return jsonify([
        {
            "user_id": u.user_id,
            "name": u.name,
            "phone": u.phone,
            "role": u.role,
            "is_online": u.is_online,
            "last_login_at": str(u.last_login_at)
                if u.last_login_at else None,
            "has_pin": bool(u.pin_code),
            "failed_attempts": u.failed_attempts,
            "locked_until": str(u.locked_until)
                if u.locked_until else None
        }
        for u in users
    ])


# ==========================================
# DASHBOARD
# ==========================================

@app.route("/api/dashboard")
@role_required("admin", "staff")
def dashboard():

    total_users = User.query.count()

    total_events = Event.query.count()

    total_registrations = Registration.query.count()

    active_events = Event.query.filter(
        Event.end_time >= db.func.now()
    ).count()

    return jsonify({

        "total_users": total_users,

        "total_events": total_events,

        "total_registrations":
            total_registrations,

        "active_events":
            active_events

    })


# ==========================================
# CREATE CATEGORY
# ==========================================

@app.route(
    "/api/categories",
    methods=["POST"]
)
@role_required("admin", "staff")
def create_category():

    data = request.get_json(
        silent=True
    ) or {}

    if not data or not data.get("name"):

        return jsonify({
            "message": "Category name is required"
        }), 400

    category = Category(

        name=data.get("name"),

        description=data.get(
            "description"
        )

    )

    db.session.add(category)

    create_audit_log(
        int(get_jwt_identity()),
        f"Created category: {category.name}"
    )

    db.session.commit()

    return jsonify({

        "message":
            "Category created successfully",

        "category_id":
            category.category_id

    }), 201


# ==========================================
# GET ALL CATEGORIES
# ==========================================

@app.route(
    "/api/categories",
    methods=["GET"]
)
def get_categories():

    categories = Category.query.all()

    return jsonify([

        {

            "category_id":
                category.category_id,

            "name":
                category.name,

            "description":
                category.description

        }

        for category in categories

    ])


# ==========================================
# UPDATE CATEGORY
# ==========================================

@app.route(
    "/api/categories/<int:category_id>",
    methods=["PUT"]
)
@role_required("admin", "staff")
def update_category(category_id):

    category = Category.query.get_or_404(
        category_id
    )

    data = request.get_json(
        silent=True
    ) or {}

    category.name = data.get(
        "name",
        category.name
    )

    category.description = data.get(
        "description",
        category.description
    )

    create_audit_log(
        int(get_jwt_identity()),
        f"Updated category ID: {category_id}"
    )

    db.session.commit()

    return jsonify({
        "message": "Category updated successfully"
    })


# ==========================================
# DELETE CATEGORY
# ==========================================

@app.route(
    "/api/categories/<int:category_id>",
    methods=["DELETE"]
)
@role_required("admin")
def delete_category(category_id):

    category = Category.query.get_or_404(
        category_id
    )

    category_name = category.name

    db.session.delete(category)

    create_audit_log(
        int(get_jwt_identity()),
        f"Deleted category: {category_name}"
    )

    db.session.commit()

    return jsonify({
        "message": "Category deleted successfully"
    })


# ==========================================
# CREATE LOCATION
# ==========================================

@app.route(
    "/api/locations",
    methods=["POST"]
)
@role_required("admin", "staff")
def create_location():

    data = request.get_json(
        silent=True
    ) or {}

    location = Location(

        address=data.get(
            "address"
        ),

        zip=data.get(
            "zip"
        ),

        map_link=data.get(
            "map_link"
        )

    )

    db.session.add(location)

    create_audit_log(
        int(get_jwt_identity()),
        f"Created location: {location.address}"
    )

    db.session.commit()

    return jsonify({

        "message":
            "Location created successfully",

        "location_id":
            location.location_id

    }), 201


# ==========================================
# GET LOCATIONS
# ==========================================

@app.route(
    "/api/locations",
    methods=["GET"]
)
def get_locations():

    locations = Location.query.all()

    return jsonify([

        {

            "location_id":
                location.location_id,

            "address":
                location.address,

            "zip":
                location.zip,

            "map_link":
                location.map_link

        }

        for location in locations

    ])


# ==========================================
# UPDATE LOCATION
# ==========================================

@app.route(
    "/api/locations/<int:location_id>",
    methods=["PUT"]
)
@role_required("admin", "staff")
def update_location(location_id):

    location = Location.query.get_or_404(
        location_id
    )

    data = request.get_json(
        silent=True
    ) or {}

    location.address = data.get(
        "address",
        location.address
    )

    location.zip = data.get(
        "zip",
        location.zip
    )

    location.map_link = data.get(
        "map_link",
        location.map_link
    )

    create_audit_log(
        int(get_jwt_identity()),
        f"Updated location ID: {location_id}"
    )

    db.session.commit()

    return jsonify({
        "message": "Location updated successfully"
    })


# ==========================================
# DELETE LOCATION
# ==========================================

@app.route(
    "/api/locations/<int:location_id>",
    methods=["DELETE"]
)
@role_required("admin")
def delete_location(location_id):

    location = Location.query.get_or_404(
        location_id
    )

    db.session.delete(location)

    create_audit_log(
        int(get_jwt_identity()),
        f"Deleted location ID: {location_id}"
    )

    db.session.commit()

    return jsonify({
        "message": "Location deleted successfully"
    })


# ==========================================
# CREATE RESOURCE
# ==========================================

@app.route(
    "/api/resources",
    methods=["POST"]
)
@role_required("admin", "staff")
def create_resource():

    data = request.get_json(
        silent=True
    ) or {}

    resource = Resource(

        name=data.get(
            "name"
        ),

        type=data.get(
            "type"
        ),

        status=data.get(
            "status",
            "Available"
        )

    )

    db.session.add(resource)

    create_audit_log(
        int(get_jwt_identity()),
        f"Created resource: {resource.name}"
    )

    db.session.commit()

    return jsonify({

        "message":
            "Resource created successfully",

        "resource_id":
            resource.resource_id

    }), 201


# ==========================================
# GET RESOURCES
# ==========================================

@app.route(
    "/api/resources",
    methods=["GET"]
)
def get_resources():

    resources = Resource.query.all()

    return jsonify([

        {

            "resource_id":
                resource.resource_id,

            "name":
                resource.name,

            "type":
                resource.type,

            "status":
                resource.status

        }

        for resource in resources

    ])


# ==========================================
# UPDATE RESOURCE
# ==========================================

@app.route(
    "/api/resources/<int:resource_id>",
    methods=["PUT"]
)
@role_required("admin", "staff")
def update_resource(resource_id):

    resource = Resource.query.get_or_404(
        resource_id
    )

    data = request.get_json(
        silent=True
    ) or {}

    resource.name = data.get(
        "name",
        resource.name
    )

    resource.type = data.get(
        "type",
        resource.type
    )

    resource.status = data.get(
        "status",
        resource.status
    )

    create_audit_log(
        int(get_jwt_identity()),
        f"Updated resource ID: {resource_id}"
    )

    db.session.commit()

    return jsonify({
        "message": "Resource updated successfully"
    })


# ==========================================
# DELETE RESOURCE
# ==========================================

@app.route(
    "/api/resources/<int:resource_id>",
    methods=["DELETE"]
)
@role_required("admin")
def delete_resource(resource_id):

    resource = Resource.query.get_or_404(
        resource_id
    )

    db.session.delete(resource)

    create_audit_log(
        int(get_jwt_identity()),
        f"Deleted resource ID: {resource_id}"
    )

    db.session.commit()

    return jsonify({
        "message": "Resource deleted successfully"
    })


# ==========================================
# CREATE ANNOUNCEMENT
# ==========================================

@app.route(
    "/api/announcements",
    methods=["POST"]
)
@role_required("admin", "staff")
def create_announcement():

    data = request.get_json(
        silent=True
    ) or {}

    if not data or not data.get("title"):

        return jsonify({
            "message":
                "Announcement title is required"
        }), 400

    announcement = Announcement(

        posted_by=int(
            get_jwt_identity()
        ),

        title=data.get(
            "title"
        ),

        content=data.get(
            "content"
        ),

        schedule=data.get(
            "schedule"
        )

    )

    db.session.add(announcement)

    create_audit_log(
        int(get_jwt_identity()),
        f"Created announcement: {announcement.title}"
    )

    db.session.commit()

    return jsonify({

        "message":
            "Announcement created successfully",

        "announcement_id":
            announcement.announcement_id

    }), 201


# ==========================================
# GET ANNOUNCEMENTS
# ==========================================

@app.route(
    "/api/announcements",
    methods=["GET"]
)
def get_announcements():

    announcements = Announcement.query.all()

    return jsonify([

        {

            "announcement_id":
                announcement.announcement_id,

            "posted_by":
                announcement.posted_by,

            "title":
                announcement.title,

            "content":
                announcement.content,

            "time_created":
                str(
                    announcement.time_created
                ),

            "schedule":
                str(
                    announcement.schedule
                )
                if announcement.schedule
                else None

        }

        for announcement in announcements

    ])


# ==========================================
# GET ONE ANNOUNCEMENT
# ==========================================

@app.route(
    "/api/announcements/<int:announcement_id>",
    methods=["GET"]
)
def get_announcement(announcement_id):

    announcement = Announcement.query.get_or_404(
        announcement_id
    )

    return jsonify({

        "announcement_id":
            announcement.announcement_id,

        "posted_by":
            announcement.posted_by,

        "title":
            announcement.title,

        "content":
            announcement.content,

        "time_created":
            str(
                announcement.time_created
            ),

        "schedule":
            str(
                announcement.schedule
            )
            if announcement.schedule
            else None

    })


# ==========================================
# UPDATE ANNOUNCEMENT
# ==========================================

@app.route(
    "/api/announcements/<int:announcement_id>",
    methods=["PUT"]
)
@role_required("admin", "staff")
def update_announcement(announcement_id):

    announcement = Announcement.query.get_or_404(
        announcement_id
    )

    data = request.get_json(
        silent=True
    ) or {}

    announcement.title = data.get(
        "title",
        announcement.title
    )

    announcement.content = data.get(
        "content",
        announcement.content
    )

    create_audit_log(
        int(get_jwt_identity()),
        f"Updated announcement ID: {announcement_id}"
    )

    db.session.commit()

    return jsonify({
        "message":
            "Announcement updated successfully"
    })


# ==========================================
# DELETE ANNOUNCEMENT
# ==========================================

@app.route(
    "/api/announcements/<int:announcement_id>",
    methods=["DELETE"]
)
@role_required("admin")
def delete_announcement(announcement_id):

    announcement = Announcement.query.get_or_404(
        announcement_id
    )

    db.session.delete(announcement)

    create_audit_log(
        int(get_jwt_identity()),
        f"Deleted announcement ID: {announcement_id}"
    )

    db.session.commit()

    return jsonify({
        "message":
            "Announcement deleted successfully"
    })


# ==========================================
# MY REGISTRATIONS
# ==========================================

@app.route(
    "/api/registrations/me",
    methods=["GET"]
)
@role_required("attendee")
def my_registrations():

    user_id = int(
        get_jwt_identity()
    )

    registrations = Registration.query.filter_by(
        user_id=user_id
    ).order_by(
        Registration.registration_time.desc()
    ).all()

    return jsonify([

        {
            "registration_id":
                r.registration_id,

            "event_id":
                r.event_id,

            "title":
                r.event.title
                if r.event
                else None,

            "status":
                r.status,

            "registration_time":
                str(r.registration_time)

        }

        for r in registrations

    ])


# ==========================================
# RUN FLASK APPLICATION
# ==========================================

if __name__ == "__main__":

    app.run(
        debug=True,
        port=5000
    )