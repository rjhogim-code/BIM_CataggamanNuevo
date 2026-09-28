const express = require("express");
const cors = require("cors");
const path = require("path");
const db = require("./db/database");

const app = express();
const PORT = 3000;

app.use(cors());
app.use(express.json());

app.use(express.static(__dirname));

app.get("/api/test", (req, res) => {

    db.query("SELECT 1 AS connected", (err, result) => {

        if (err) {
            return res.status(500).json(err);
        }

        res.json({
            message: "Database Connected",
            data: result
        });
    });

});

app.listen(PORT, () => {
    console.log(`Server running at http://localhost:${PORT}`);
});