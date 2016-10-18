<?php 

//Reject if accessed directly
defined( 'ABSPATH' ) || die( 'Our survey says: ... X.' );

if( ! class_exists( 'Bop_Collection' ) ):

abstract class Bop_Collection{
  
  public $query_vars;
  
  public $default_vars = [];
  
  protected $_parsed_vars = [
    'select'=>[],
    'join'=>[],
    'where'=>['relationship'=>'AND'],
    'groupby'=>[],
    'having'=>[],
    'orderby'=>[],
    'offset'=>0,
    'limit'=>0
  ];

  protected $_collection_hash = '';
  
  protected $_queried = false;
  
  abstract protected $_primary_table;
  
  abstract public $item_class;
  
  abstract protected $_item_cache_group;
  
  abstract protected $_collection_cache_group;

  public $cache = true;

  public $overwrite_cache = false;

  public $collection;
  
  public $collection_ids;
  
  public function __construct( $query = [] ){
    if ( ! empty( $query ) ) {
      $this->query( $query );
    }
  }
  
  public function init(){}
  
  public function query( $query ){
    $this->init();
    
    $this->query_vars = $query;
    $this->parse_query();
  }
  
  public function parse_query(){
    $this->_queried = false;
  }
  
  public function get_collection(){
    if( $this->_queried )
      return $this->collection;
    
    $this->_queried = true;
    
    $current_hash = md5( serialize( $this->_parsed_vars ) );
    if( ! $this->overwrite_cache && $this->cache ){
      $cache_result = wp_cache_get( $current_hash, $this->_collection_cache_group );  
      
      if( $cache_result ){
        $this->collection_ids = $cache_result;
        $class = $this->item_class;
        foreach( $this->collection_ids as $id ){
          if( $row = wp_cache_get( $id, $this->_item_cache_group . '_data' ) ){
            $this->collection[] = new $class()->fill_object( $row );
          }else{
            $this->collection[] = new $class( $id );
          }
        }
        return $this->collection;
      }
    }

    $this->_get_collection();

    $this->_collection_hash = $current_hash;
    $this->overwrite_cache = false;
    
    if( $this->cache )
      wp_cache_set( $current_hash, $this->collection_ids, $this->_collection_cache_group );
      
    return $this->collection;
  }
  
  protected function _get_collection(){
    global $wpdb;
    
    $this->collection = [];
    $this->collection_ids = [];
    
    $v = &$this->_parsed_vars;
    
    $select = "";
    foreach( $v['select'] as $col ){
      $select .= "t.{$col} AS {$col}";
    }
    $q = "SELECT $select";
    $q .= "FROM {$wpdb->bop_bookings} AS t";
    
    $joins = "";
    foreach( $v['join'] as $j ){
      $joins .= "\n{$j['type']} JOIN {$j['table']} AS {$j['alias']} ON ({$j['native_field'] = $j['foreign_field']})";
    }
    $q .= $joins;
    
    $wheres = $this->_fill_where_clause( $v['where'] );
    if( $wheres )
      $q .= "\nWHERE 1=1 " . $wheres;
    
    $groupbys = implode( ", ", $v['groupby'] );
    if( $groupbys )
      $q .= "\nGROUP BY {$groupbys}";
      
    //!having
    
    if( $v['orderby'] ){
      $obs = [];
      foreach( $v['orderby'] as $ob ){
        $obs[] = "{$ob['field']} {$ob['dir']}";
      }
      $q .= "\nORDER BY " . implode( ", ", $obs );
    }
    
    if( $v['limit'] )
      $q .= "\nLIMIT {$v['limit']}";
      
    if( $v['offset'] )
      $q .= "\nOFFSET {$v['offset']}";
    
    $rows = $wpdb->get_results( $q, ARRAY_A );
    
    foreach( $rows as $row ){
      $this->collection_ids[] = $row['id'];
    }
    
    $rows = $this->_extend_raw_data( $rows );
    
    $class = $this->item_class;
    foreach( $rows as $row ){
      $this->collection[] = new $class()->fill_object( $row );
      
      if( $this->cache )
        wp_cache_set( $booking->id, $row, $this->_item_cache_group . '_data' );
    }
  }
  
  protected function _extend_raw_data( $rows ){
    return $rows;
  }
  
  protected function _fill_where_clause( $clause ){
    $output = "";
    foreach( $clause as $w ){
      if( isset( $w['field'] ) ){
        $output .= "\n{$clause['relation']} CAST({$w['table']}.{$w['field']} AS {$w['cast']}) {$w['compare']} CAST({$w['value']} AS {$w['cast']})";
      }else{
        $ouput .= "\n{$clause['relation']} (" . $this->_fill_where_clause( $w ) . ")";
      }
    }
    return $output;
  }
  
}

endif; //class exists
