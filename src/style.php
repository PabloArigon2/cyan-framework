<?php

enum Colors {
    case RED;
    case BLUE;
    case YELLOW;
    case GREEN;
    case ORANGE;
}

class Elements {
    public static function RowBox(string $text, Colors $color) {
        switch ($color) {
            case Colors::RED:
                return '<div class="bx box-red">'.$text.'</div>';
            case Colors::GREEN:
                return '<div class="bx box-green">'.$text.'</div>';
            case Colors::YELLOW:
                return '<div class="bx box-yellow">'.$text.'</div>';
            case Colors::ORANGE:
                return '<div class="bx box-orange">'.$text.'</div>';
            case Colors::BLUE:
                return '<div class="bx box-blue">'.$text.'</div>'; 
        }
    }
}
?>
