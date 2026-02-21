import { useEffect, useMemo, useState } from "react";
import { useAuth } from "../auth/AuthContext";
import http from "../api/http";
import Card from "../components/Card";

const WEEK_DAYS = ["Pon", "Uto", "Sre", "Čet", "Pet", "Sub", "Ned"];

function toKey(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function isSameDate(a, b) {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

function startOfWeek(date) {
  const d = new Date(date);
  const day = d.getDay(); 
  const diff = (day + 6) % 7; 
  d.setDate(d.getDate() - diff);
  d.setHours(0, 0, 0, 0);
  return d;
}

function eventDateKeyFromStart(start) {
  if (typeof start === "string") {
    const m = start.match(/^(\d{4}-\d{2}-\d{2})/);
    if (m) return m[1];
  }

  const parsed = new Date(start);
  if (Number.isNaN(parsed.getTime())) return null;
  return toKey(parsed);
}

function eventTimeLabel(start) {
  if (typeof start === "string") {
    const m = start.match(/T(\d{2}:\d{2})/);
    if (m) return m[1];
  }

  const parsed = new Date(start);
  if (Number.isNaN(parsed.getTime())) return "--:--";

  return parsed.toLocaleTimeString("sr-RS", {
    hour: "2-digit",
    minute: "2-digit",
  });
}

function getMonthGrid(date) {
  const year = date.getFullYear();
  const month = date.getMonth();
  const first = new Date(year, month, 1);
  const gridStart = startOfWeek(first);

  return Array.from({ length: 42 }, (_, i) => {
    const d = new Date(gridStart);
    d.setDate(gridStart.getDate() + i);
    return d;
  });
}

export default function Pocetna() {
  const { user } = useAuth();
  const isAdmin = user?.uloga === "ADMIN";
  const showCalendar = ["STUDENT", "PROFESOR"].includes(user?.uloga || "");

  const [counts, setCounts] = useState({
    predmeti: 0,
    zadaci: 0,
    predaje: 0,
  });

  const [calendarData, setCalendarData] = useState([]);
  const [calendarConnected, setCalendarConnected] = useState(false);
  const [focusDate, setFocusDate] = useState(new Date());
  const [todayMeta, setTodayMeta] = useState(null);

  useEffect(() => {
    if (!user?.uloga) return;

    (async () => {
      try {
        const [predmetiRes, zadaciRes, predajeRes] = await Promise.all([
          http.get(isAdmin ? "/predmeti" : "/predmeti/moji"),
          http.get(isAdmin ? "/zadaci" : "/zadaci/moji"),
          isAdmin
            ? http.get("/predaje")
            : user?.uloga === "PROFESOR"
            ? http.get("/predaje/za-moje-predmete")
            : http.get("/predaje/moje"),
        ]);

        const predmeti = predmetiRes.data?.data || predmetiRes.data || [];
        const zadaci = zadaciRes.data?.data || zadaciRes.data || [];
        const predaje = predajeRes.data?.data || predajeRes.data || [];

        setCounts({
          predmeti: predmeti.length,
          zadaci: zadaci.length,
          predaje: predaje.length,
        });
      } catch {
        setCounts({ predmeti: 0, zadaci: 0, predaje: 0 });
      }
    })();
  }, [user?.uloga, isAdmin]);

  useEffect(() => {
    if (!showCalendar) return;

    (async () => {
      try {
        const res = await http.get("/kalendar/rokovi");
        setCalendarData(res.data?.data || []);
        setCalendarConnected(Boolean(res.data?.meta?.external_calendar_connected));
        setTodayMeta(res.data?.meta?.today || null);
      } catch {
        setCalendarData([]);
        setCalendarConnected(false);
        setTodayMeta(null);
      }
    })();
  }, [showCalendar]);

  const todayLabel = useMemo(() => {
    if (todayMeta?.day_name && todayMeta?.date) {
      return `${todayMeta.day_name}, ${todayMeta.date}`;
    }

    return new Date().toLocaleDateString("sr-RS", {
      weekday: "long",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    });
  }, [todayMeta]);

  const monthGrid = useMemo(() => getMonthGrid(focusDate), [focusDate]);

  const eventsByDate = useMemo(() => {
    const map = new Map();

    calendarData.forEach((event) => {
      const key = eventDateKeyFromStart(event.start);
      if (!key) return;

      if (!map.has(key)) map.set(key, []);
      map.get(key).push(event);
    });

    map.forEach((list) => {
      list.sort((a, b) => new Date(a.start).getTime() - new Date(b.start).getTime());
    });

    return map;
  }, [calendarData]);

  const monthLabel = focusDate.toLocaleDateString("sr-RS", {
    month: "long",
    year: "numeric",
  });

  return (
    <div style={{ padding: 16, display: "grid", gap: 12 }}>
      <h2>Početna</h2>

      <Card>
        <div>
          <b>Korisnik:</b> {user?.ime} {user?.prezime}
        </div>
        <div>
          <b>Uloga:</b> {user?.uloga}
        </div>
        <div>
          <b>Email:</b> {user?.email}
        </div>
      </Card>

      <div
        style={{
          display: "grid",
          gap: 12,
          gridTemplateColumns: "repeat(3, minmax(0, 1fr))",
        }}
      >
        <Card>
          <div style={{ fontSize: 13, color: "#555" }}>
            {isAdmin ? "Predmeti" : "Moji predmeti"}
          </div>
          <div style={{ fontSize: 28, fontWeight: 800 }}>{counts.predmeti}</div>
        </Card>

        <Card>
          <div style={{ fontSize: 13, color: "#555" }}>
            {isAdmin ? "Zadaci" : "Moji zadaci"}
          </div>
          <div style={{ fontSize: 28, fontWeight: 800 }}>{counts.zadaci}</div>
        </Card>

        <Card>
          <div style={{ fontSize: 13, color: "#555" }}>
            {isAdmin ? "Predaje" : "Moje predaje"}
          </div>
          <div style={{ fontSize: 28, fontWeight: 800 }}>{counts.predaje}</div>
        </Card>
      </div>

      {showCalendar && (
        <Card>
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: 8,
              marginBottom: 10,
              flexWrap: "wrap",
            }}
          >
            <h3 style={{ margin: 0 }}>Kalendar rokova</h3>

            <div style={{ display: "flex", gap: 6 }}>
              <button
                type="button"
                onClick={() =>
                  setFocusDate((prev) => new Date(prev.getFullYear(), prev.getMonth() - 1, 1))
                }
              >
                ← Prethodni
              </button>

              <button type="button" onClick={() => setFocusDate(new Date())}>
                Danas
              </button>

              <button
                type="button"
                onClick={() =>
                  setFocusDate((prev) => new Date(prev.getFullYear(), prev.getMonth() + 1, 1))
                }
              >
                Sledeći →
              </button>
            </div>
          </div>

          <div style={{ fontWeight: 700, textTransform: "capitalize", marginBottom: 8 }}>
            {monthLabel}
          </div>

          <div style={{ fontSize: 14, color: "#333", marginBottom: 8 }}>
            Danas je: <b style={{ textTransform: "capitalize" }}>{todayLabel}</b>
          </div>

         {/*  <div style={{ fontSize: 13, color: "#666", marginBottom: 10 }}>
            Integracija sa eksternim kalendarom: {calendarConnected ? "aktivna" : "nije podešena"}
          </div> */}

          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(7, minmax(0, 1fr))",
              gap: 6,
            }}
          >
            {WEEK_DAYS.map((day) => (
              <div
                key={day}
                style={{
                  fontWeight: 700,
                  textAlign: "center",
                  padding: "4px 0",
                }}
              >
                {day}
              </div>
            ))}

            {monthGrid.map((dayDate) => {
              const key = toKey(dayDate);
              const dayEvents = eventsByDate.get(key) || [];
              const isCurrentMonth = dayDate.getMonth() === focusDate.getMonth();
              const isToday = isSameDate(dayDate, new Date());

              return (
                <div
                  key={key}
                  style={{
                    minHeight: 112,
                    border: isToday ? "2px solid #2563eb" : "1px solid #ddd",
                    borderRadius: 10,
                    padding: 8,
                    background: isCurrentMonth ? "#fff" : "#f8f8f8",
                    opacity: isCurrentMonth ? 1 : 0.6,
                  }}
                >
                  <div
                    style={{
                      fontWeight: isToday ? 800 : 600,
                      color: isToday ? "#2563eb" : "#111",
                    }}
                  >
                    {dayDate.getDate()} {isToday ? "(danas)" : ""}
                  </div>

                  <div style={{ display: "grid", gap: 4, marginTop: 6 }}>
                    {dayEvents.slice(0, 3).map((event) => {
                      const rokTekst =
                        event.source === "external_calendar"
                          ? null
                          : event.all_day
                          ? "Rok do kraja dana"
                          : `Rok do ${eventTimeLabel(event.start)}`;

                      return (
                        <div
                          key={event.id ?? `${event.title}-${event.start}`}
                          style={{
                            fontSize: 12,
                            borderLeft: `3px solid ${
                              event.source === "external_calendar" ? "#dc2626" : "#111"
                            }`,
                            background:
                              event.source === "external_calendar" ? "#fef2f2" : "#f3f4f6",
                            padding: "3px 6px",
                            borderRadius: 6,
                          }}
                        >
                          <div style={{ fontWeight: 600, lineHeight: 1.2 }}>{event.title}</div>
                          {rokTekst && <div style={{ color: "#555" }}>{rokTekst}</div>}
                        </div>
                      );
                    })}

                    {dayEvents.length > 3 && (
                      <div style={{ fontSize: 12, color: "#666" }}>
                        + još {dayEvents.length - 3}
                      </div>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </Card>
      )}

      <Card>
        <div style={{ fontSize: 14, color: "#444" }}>
          Ova aplikacija koristi REST API + Sanctum Bearer token. Prikaz podataka je filtriran po ulozi
          (student/profesor/admin).
        </div>
      </Card>
    </div>
  );
}