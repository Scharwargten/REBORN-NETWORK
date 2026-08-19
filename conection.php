<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "ultra_rumble";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Erro: " . $conn->connect_error);
}
?>

<?php
session_start();
include("config.php");

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$senha = $_POST['senha'];

$stmt = $conn->prepare("SELECT id, nome, senha, tipo FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (password_verify($senha, $user['senha'])) {
        $_SESSION['id'] = $user['id'];
        $_SESSION['nome'] = $user['nome'];
        $_SESSION['tipo'] = $user['tipo'];
        header("Location: dashboard.php");
        exit;
    } else {
        echo "Senha incorreta";
    }
} else {
    echo "Usuário não encontrado";
}
?>

<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: index.html");
    exit;
}
?>
<h1>Bem-vindo, <?php echo $_SESSION['nome']; ?>!</h1>
<a href="logout.php">Sair</a>

<?php
session_start();
session_destroy();
header("Location: index.html");
?>

<?php
session_start();
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] != 'admin') {
    die("Acesso negado");
}
?>
<h1>Painel Admin</h1>
<a href="add_produto.php">Adicionar Produto</a><br>
<a href="listar_produtos.php">Gerenciar Produtos</a><br>
<a href="pedidos.php">Ver Pedidos</a>

<form action="salvar_produto.php" method="POST">
  <input type="text" name="nome" placeholder="Nome"><br>
  <textarea name="descricao"></textarea><br>
  <input type="number" step="0.01" name="preco"><br>
  <input type="text" name="imagem" placeholder="URL da imagem"><br>
  <button>Salvar</button>
</form>

<?php
include("../config.php");

$nome = $_POST['nome'];
$desc = $_POST['descricao'];
$preco = $_POST['preco'];
$img = $_POST['imagem'];

$stmt = $conn->prepare("INSERT INTO produtos (nome, descricao, preco, imagem) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssds", $nome, $desc, $preco, $img);
$stmt->execute();

header("Location: admin.php");
?>

<?php
include("config.php");
$result = $conn->query("SELECT * FROM produtos");
?>
<div class="container mt-5">
  <div class="row">
<?php while($p = $result->fetch_assoc()) { ?>
<div class="col-md-4">
  <div class="card p-3">
    <img src="<?= $p['imagem'] ?>" class="img-fluid">
    <h3><?= $p['nome'] ?></h3>
    <p><?= $p['descricao'] ?></p>
    <strong>R$ <?= $p['preco'] ?></strong>
    <a href="comprar.php?id=<?= $p['id'] ?>" class="btn btn-warning">Comprar</a>
  </div>
</div>
<?php } ?>
  </div>
</div>

<?php
session_start();
include("config.php");

if (!isset($_SESSION['id'])) {
    header("Location: login.html");
    exit;
}

$produto_id = intval($_GET['id']);
$quantidade = 1;

$stmt = $conn->prepare("SELECT preco FROM produtos WHERE id = ?");
$stmt->bind_param("i", $produto_id);
$stmt->execute();
$res = $stmt->get_result();
$produto = $res->fetch_assoc();

if ($produto) {
    $stmt = $conn->prepare("INSERT INTO pedidos (usuario_id, total, criado_em) VALUES (?, 0, NOW())");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $pedido_id = $stmt->insert_id;

    $stmt = $conn->prepare("INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiid", $pedido_id, $produto_id, $quantidade, $produto['preco']);
    $stmt->execute();

    $total = $quantidade * $produto['preco'];
    $stmt = $conn->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
    $stmt->bind_param("di", $total, $pedido_id);
    $stmt->execute();

    echo "Compra realizada com sucesso!";
} else {
    echo "Produto não encontrado.";
}
?>

<?php
session_start();
include("../config.php");

if ($_SESSION['tipo'] != 'admin') {
    die("Acesso negado");
}

$sql = "SELECT p.id, u.nome, p.total, p.criado_em
        FROM pedidos p
        JOIN usuarios u ON u.id = p.usuario_id
        ORDER BY p.criado_em DESC";
$res = $conn->query($sql);

while($pedido = $res->fetch_assoc()) {
    echo "Pedido #".$pedido['id']." - ".$pedido['nome']." - R$".$pedido['total']." - ".$pedido['criado_em']."<br>";

    $stmt = $conn->prepare("SELECT pr.nome, i.quantidade, i.preco_unitario
                            FROM pedido_itens i
                            JOIN produtos pr ON pr.id = i.produto_id
                            WHERE i.pedido_id = ?");
    $stmt->bind_param("i", $pedido['id']);
    $stmt->execute();
    $itens = $stmt->get_result();

    while($item = $itens->fetch_assoc()) {
        echo "- ".$item['nome']." (".$item['quantidade']." x R$".$item['preco_unitario'].")<br>";
    }
    echo "<hr>";
}
?>
