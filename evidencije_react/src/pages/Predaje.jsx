import { useEffect, useMemo, useState } from "react";
import http from "../api/http";
import Card from "../components/Card";
import Button from "../components/Button";
import Input from "../components/Input";
import Modal from "../components/Modal";
import { useAuth } from "../auth/AuthContext";

export default function Predaje() {
  const { user } = useAuth();
  const isAdmin = user?.uloga === "ADMIN";
  const isProfesor = user?.uloga === "PROFESOR";
  const isStudent = user?.uloga === "STUDENT";

  const [items, setItems] = useState([]);
  const [err, setErr] = useState("");
  const [busyId, setBusyId] = useState(null);

  const [q, setQ] = useState("");

  const [open, setOpen] = useState(false);
  const [selected, setSelected] = useState(null);

  const [zadaci, setZadaci] = useState([]);
  const [zadatakId, setZadatakId] = useState("");
  const [file, setFile] = useState(null);
  const [fileInputKey, setFileInputKey] = useState(0);
  const [uploadErr, setUploadErr] = useState("");
  const [uploading, setUploading] = useState(false);

  const [edit, setEdit] = useState({ status: "", ocena: "", komentar: "" });

  async function load() {
    setErr("");
    try {
      const endpoint = isAdmin
        ? "/predaje"
        : isProfesor
        ? "/predaje/za-moje-predmete"
        : "/predaje/moje";

      const res = await http.get(endpoint);
      setItems(res.data.data || res.data || []);
    } catch (e) {
      setErr(e?.response?.data?.message || "Greška pri učitavanju");
    }
  }

  useEffect(() => {
    if (!user?.uloga) return;
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.uloga]);

  useEffect(() => {
    if (!isStudent) return;
    (async () => {
      try {
        const res = await http.get("/zadaci/moji");
        setZadaci(res.data.data || res.data || []);
      } catch {
        setZadaci([]);
      }
    })();
  }, [isStudent]);

  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase();
    if (!s) return items;

    return items.filter((p) => {
      const hay =
        `${p.id} ${p.status ?? ""} ${p.komentar ?? ""} ${p.zadatak?.naslov ?? ""} ${
          p.student?.email ?? ""
        }`.toLowerCase();
      return hay.includes(s);
    });
  }, [items, q]);

  async function openFile(predaja) {
    if (!predaja?.id) return;
    try {
      const res = await http.get(`/predaje/${predaja.id}/file`, {
        responseType: "blob",
      });
      const blob = new Blob([res.data], { type: res.headers["content-type"] });
      const url = window.URL.createObjectURL(blob);
      window.open(url, "_blank", "noopener");
      window.setTimeout(() => window.URL.revokeObjectURL(url), 10000);
    } catch (e) {
      alert(e?.response?.data?.message || "Neuspešno otvaranje fajla.");
    }
  }

  function openDetails(p) {
    setSelected(p);
    setEdit({
      status: p.status ?? "",
      ocena: p.ocena ?? "",
      komentar: p.komentar ?? "",
    });
    setOpen(true);
  }

  async function pokreniPlagijat(predajaId) {
    setBusyId(predajaId);
    try {
      await http.post(`/predaje/${predajaId}/provera-plagijata`);
      await load(); 
    } catch (e) {
      alert(e?.response?.data?.message || "Neuspešno");
    } finally {
      setBusyId(null);
    }
  }

  async function obrisiPredaju(id) {
    setBusyId(id);
    try {
      await http.delete(`/predaje/${id}`);
      await load();
    } catch (e) {
      alert(e?.response?.data?.message || "Greška pri brisanju predaje");
    } finally {
      setBusyId(null);
    }
  }

  async function submitPredaja(e) {
    e.preventDefault();
    setUploadErr("");

    if (!zadatakId) {
      setUploadErr("Izaberi zadatak.");
      return;
    }
    if (!file) {
      setUploadErr("Dodaj fajl za predaju.");
      return;
    }

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append("zadatak_id", zadatakId);
      formData.append("file", file);
      await http.post("/predaje", formData);
      setZadatakId("");
      setFile(null);
      setFileInputKey((k) => k + 1);
      await load();
    } catch (e2) {
      setUploadErr(e2?.response?.data?.message || "Greška pri slanju predaje.");
    } finally {
      setUploading(false);
    }
  }

  async function sacuvajOcenu() {
    if (!selected) return;

    const allowed = ["PREDATO", "OCENJENO", "VRAĆENO", "ZAKAŠNJENO"];
    if (!allowed.includes(edit.status)) {
      alert("Status mora biti: " + allowed.join(", "));
      return;
    }
    if (edit.ocena !== "") {
      const n = Number(edit.ocena);
      if (Number.isNaN(n) || n < 0 || n > 10) {
        alert("Ocena mora biti broj od 0 do 10.");
        return;
      }
    }

    setBusyId(selected.id);
    try {
      await http.put(`/predaje/${selected.id}`, {
        status: edit.status,
        ocena: edit.ocena === "" ? null : Number(edit.ocena),
        komentar: edit.komentar,
      });
      await load();
      setOpen(false);
    } catch (e) {
      alert(e?.response?.data?.message || "Neuspešno čuvanje");
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div style={{ padding: 16, display: "grid", gap: 12 }}>
      <h2>{isAdmin ? "Predaje" : "Moje predaje"}</h2>

      {isStudent && (
        <Card>
          <h3>Nova predaja</h3>
          <form onSubmit={submitPredaja} style={{ display: "grid", gap: 10 }}>
            <div style={{ display: "grid", gap: 6 }}>
              <label>Zadatak</label>
              <select
                value={zadatakId}
                onChange={(e) => setZadatakId(e.target.value)}
                style={{
                  padding: "10px 12px",
                  borderRadius: 10,
                  border: "1px solid #ddd",
                }}
              >
                <option value="">-- Izaberi zadatak --</option>
                {zadaci.map((z) => (
                  <option key={z.id} value={z.id}>
                    {z.naslov}
                  </option>
                ))}
              </select>
            </div>

            <div style={{ display: "grid", gap: 6 }}>
              <label>Fajl rada</label>
              <input
                key={fileInputKey}
                type="file"
                accept=".pdf,.doc,.docx,.txt,.zip"
                onChange={(e) => setFile(e.target.files?.[0] || null)}
              />
            </div>

            {uploadErr && <div style={{ color: "crimson" }}>{uploadErr}</div>}

            <Button type="submit" disabled={uploading || !zadatakId || !file}>
              {uploading ? "Šaljem..." : "Pošalji predaju"}
            </Button>
          </form>
        </Card>
      )}

      <div style={{ maxWidth: 420 }}>
        <Input
          placeholder="Pretraga (id/status/komentar/naslov/email)..."
          value={q}
          onChange={(e) => setQ(e.target.value)}
        />
      </div>

      {err && <div style={{ color: "crimson" }}>{err}</div>}

      {filtered.map((p) => (
        <Card key={p.id}>
          <div
            style={{
              display: "flex",
              justifyContent: "space-between",
              gap: 12,
            }}
          >
            <div style={{ display: "grid", gap: 4 }}>
              <div>
                <b>Predaja #{p.id}</b>
              </div>
              <div>Status: {p.status}</div>
              <div>Ocena: {p.ocena ?? "-"}</div>
              <div>Komentar: {p.komentar ?? "-"}</div>
              {p.zadatak?.naslov && <div>Zadatak: {p.zadatak.naslov}</div>}
            </div>

            <div style={{ display: "grid", gap: 8, alignContent: "start" }}>
              <Button onClick={() => openDetails(p)}>Detalji</Button>

              {isProfesor && (
                <Button
                  onClick={() => pokreniPlagijat(p.id)}
                  disabled={busyId === p.id}
                >
                  {busyId === p.id ? "Proveravam..." : "Proveri plagijat"}
                </Button>
              )}

              {isAdmin && (
                <Button
                  onClick={() => obrisiPredaju(p.id)}
                  disabled={busyId === p.id}
                >
                  {busyId === p.id ? "Brišem..." : "Obriši"}
                </Button>
              )}
            </div>
          </div>
        </Card>
      ))}

      {!err && filtered.length === 0 && <div>Nema podataka.</div>}

      <Modal
        open={open}
        title={selected ? `Predaja #${selected.id}` : "Predaja"}
        onClose={() => setOpen(false)}
      >
        {selected && (
          <div style={{ display: "grid", gap: 10 }}>
            <div>
              <b>Zadatak:</b> {selected.zadatak?.naslov ?? "-"}
            </div>
            <div>
             <div>
                <b>Status:</b>{" "}
                {(isProfesor) ? (
                  <select
                    value={edit.status}
                    onChange={(e) => setEdit({ ...edit, status: e.target.value })}
                  >
                    <option value="">Izaberi status</option>
                    <option value="PREDATO">PREDATO</option>
                    <option value="OCENJENO">OCENJENO</option>
                    <option value="VRAĆENO">VRAĆENO</option>
                    <option value="ZAKAŠNJENO">ZAKAŠNJENO</option>
                  </select>
                ) : (
                  <span>{selected.status ?? "-"}</span>
                )}
              </div>
            </div>
            <div>
              <b>Komentar:</b> {selected.komentar ?? "-"}
            </div>

            {(isProfesor || isAdmin) && selected.file_path && (
              <div>
                <b>Rad:</b>{" "}
                <Button onClick={() => openFile(selected)}>Otvori fajl</Button>
              </div>
            )}

            {isProfesor && (
              <div
                style={{
                  borderTop: "1px solid #eee",
                  paddingTop: 12,
                  display: "grid",
                  gap: 8,
                }}
              >
                <div style={{ fontWeight: 700 }}>Ocenjivanje (profesor)</div>

                <Input
                  placeholder="Status (PREDATO/OCENJENO/VRAĆENO/ZAKAŠNJENO)"
                  value={edit.status}
                  onChange={(e) =>
                    setEdit((x) => ({ ...x, status: e.target.value }))
                  }
                />
                <Input
                  placeholder="Ocena (0-10)"
                  value={edit.ocena}
                  onChange={(e) =>
                    setEdit((x) => ({ ...x, ocena: e.target.value }))
                  }
                />
                <Input
                  placeholder="Komentar"
                  value={edit.komentar}
                  onChange={(e) =>
                    setEdit((x) => ({ ...x, komentar: e.target.value }))
                  }
                />

                <div style={{ display: "flex", gap: 8 }}>
                  <Button
                    onClick={sacuvajOcenu}
                    disabled={busyId === selected.id}
                  >
                    {busyId === selected.id ? "Čuvam..." : "Sačuvaj"}
                  </Button>
                  <Button onClick={() => setOpen(false)}>Zatvori</Button>
                </div>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
}
